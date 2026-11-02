<?php

namespace App\Http\Controllers\Api;

use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ComplaintResource;
use App\Http\Traits\ApiResponse;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Services\NotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplaintController extends Controller
{
    use ApiResponse;

    private const DUPLICATE_WINDOW_MINUTES = 10;

    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = Complaint::query()
            ->with(['user', 'replier'])
            ->withCount('messages')
            ->latest();

        if (! $user->canManageComplaints()) {
            $query->where('user_id', $user->id);
        }

        $complaints = $query->get();

        return $this->successResponse(
            ComplaintResource::collection($complaints),
            'تم جلب التذاكر بنجاح'
        );
    }

    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->canManageComplaints()) {
            return $this->forbiddenResponse('لا يمكن لمدير النظام إرسال تذاكر');
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', 'in:support,complaint,billing,other'],
            'message' => ['required', 'string', 'max:5000'],
            'client_request_id' => ['nullable', 'string', 'max:64'],
        ], [
            'subject.required' => 'الموضوع مطلوب',
            'category.required' => 'نوع الطلب مطلوب',
            'message.required' => 'الرسالة مطلوبة',
        ]);

        $clientRequestId = isset($validated['client_request_id'])
            ? trim((string) $validated['client_request_id'])
            : null;
        if ($clientRequestId === '') {
            $clientRequestId = null;
        }

        if ($clientRequestId) {
            $existingByKey = Complaint::query()
                ->with(['user', 'replier', 'messages.user'])
                ->where('user_id', $user->id)
                ->where('client_request_id', $clientRequestId)
                ->first();

            if ($existingByKey) {
                return $this->successResponse(
                    new ComplaintResource($existingByKey),
                    'تم إرسال التذكرة مسبقاً'
                );
            }
        }

        $contentHash = Complaint::hashContent($validated['message']);
        $duplicate = Complaint::query()
            ->with(['user', 'replier', 'messages.user'])
            ->where('user_id', $user->id)
            ->where('content_hash', $contentHash)
            ->where('subject', $validated['subject'])
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
            ->latest('id')
            ->first();

        if ($duplicate) {
            return $this->successResponse(
                new ComplaintResource($duplicate),
                'تم تجاهل التكرار — نفس التذكرة موجودة بالفعل'
            );
        }

        try {
            $complaint = DB::transaction(function () use ($user, $validated, $clientRequestId, $contentHash) {
                $ticket = Complaint::create([
                    'user_id' => $user->id,
                    'subject' => $validated['subject'],
                    'category' => $validated['category'],
                    'message' => $validated['message'],
                    'client_request_id' => $clientRequestId,
                    'content_hash' => $contentHash,
                    'status' => ComplaintStatus::Pending,
                ]);

                ComplaintMessage::create([
                    'complaint_id' => $ticket->id,
                    'user_id' => $user->id,
                    'body' => $validated['message'],
                    'is_staff' => false,
                ]);

                return $ticket->load(['user', 'replier', 'messages.user']);
            });
        } catch (QueryException $e) {
            if ($clientRequestId && $this->isUniqueViolation($e)) {
                $existing = Complaint::query()
                    ->with(['user', 'replier', 'messages.user'])
                    ->where('user_id', $user->id)
                    ->where('client_request_id', $clientRequestId)
                    ->first();

                if ($existing) {
                    return $this->successResponse(
                        new ComplaintResource($existing),
                        'تم إرسال التذكرة مسبقاً'
                    );
                }
            }

            throw $e;
        }

        $this->notificationService->notifyPlatformAdmins(
            'complaint_submitted',
            'تذكرة دعم جديدة',
            "أرسل {$user->name} تذكرة جديدة: {$complaint->subject}",
            [
                'complaint_id' => $complaint->id,
                'category' => (string) $complaint->category,
                'subject' => $complaint->subject,
            ],
            $user
        );

        return $this->createdResponse(
            new ComplaintResource($complaint),
            'تم فتح التذكرة بنجاح'
        );
    }

    public function show(int $id, Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $complaint = Complaint::with(['user', 'replier', 'messages.user'])->find($id);

        if (! $complaint) {
            return $this->notFoundResponse('التذكرة غير موجودة');
        }

        if (! $user->canManageComplaints() && $complaint->user_id !== $user->id) {
            return $this->forbiddenResponse('غير مصرح بعرض هذه التذكرة');
        }

        return $this->successResponse(
            new ComplaintResource($complaint),
            'تم جلب التذكرة بنجاح'
        );
    }

    public function reply(int $id, Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $user->canManageComplaints()) {
            return $this->forbiddenResponse('غير مصرح بالرد على التذاكر');
        }

        $complaint = Complaint::with(['user', 'replier', 'messages.user'])->find($id);

        if (! $complaint) {
            return $this->notFoundResponse('التذكرة غير موجودة');
        }

        if ($complaint->status === ComplaintStatus::Closed) {
            return $this->errorResponse('التذكرة مغلقة ولا يمكن الرد عليها', 422);
        }

        $validated = $request->validate([
            'admin_reply' => ['required_without:body', 'nullable', 'string', 'max:5000'],
            'body' => ['required_without:admin_reply', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', 'in:replied,closed'],
        ], [
            'admin_reply.required_without' => 'نص الرد مطلوب',
            'body.required_without' => 'نص الرد مطلوب',
        ]);

        $body = trim((string) ($validated['body'] ?? $validated['admin_reply'] ?? ''));
        if ($body === '') {
            return $this->errorResponse('نص الرد مطلوب', 422);
        }

        $status = $validated['status'] ?? ComplaintStatus::Replied->value;

        DB::transaction(function () use ($complaint, $user, $body, $status) {
            ComplaintMessage::create([
                'complaint_id' => $complaint->id,
                'user_id' => $user->id,
                'body' => $body,
                'is_staff' => true,
            ]);

            $complaint->update([
                'admin_reply' => $body,
                'replied_by' => $user->id,
                'replied_at' => now(),
                'status' => $status,
            ]);
        });

        $complaint->refresh()->load(['user', 'replier', 'messages.user']);

        $this->notificationService->create(
            $complaint->user,
            'complaint_replied',
            'رد جديد على تذكرتك',
            "تم الرد على تذكرتك: {$complaint->subject}",
            [
                'complaint_id' => $complaint->id,
                'status' => $complaint->status->value,
            ],
            false
        );

        return $this->successResponse(
            new ComplaintResource($complaint),
            'تم إرسال الرد بنجاح'
        );
    }

    public function message(int $id, Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $complaint = Complaint::with(['user', 'replier', 'messages.user'])->find($id);

        if (! $complaint) {
            return $this->notFoundResponse('التذكرة غير موجودة');
        }

        $isStaff = $user->canManageComplaints();
        $isAuthor = $complaint->user_id === $user->id;

        if (! $isStaff && ! $isAuthor) {
            return $this->forbiddenResponse('غير مصرح بإرسال رسالة على هذه التذكرة');
        }

        if ($complaint->status === ComplaintStatus::Closed) {
            return $this->errorResponse('التذكرة مغلقة — افتح تذكرة جديدة إن احتجت', 422);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', 'in:replied,closed'],
        ], [
            'body.required' => 'نص الرسالة مطلوب',
        ]);

        $body = trim($validated['body']);
        if ($body === '') {
            return $this->errorResponse('نص الرسالة مطلوب', 422);
        }

        DB::transaction(function () use ($complaint, $user, $body, $isStaff, $validated) {
            ComplaintMessage::create([
                'complaint_id' => $complaint->id,
                'user_id' => $user->id,
                'body' => $body,
                'is_staff' => $isStaff,
            ]);

            if ($isStaff) {
                $complaint->update([
                    'admin_reply' => $body,
                    'replied_by' => $user->id,
                    'replied_at' => now(),
                    'status' => $validated['status'] ?? ComplaintStatus::Replied->value,
                ]);
            } else {
                // User follow-up reopens the ticket for staff review.
                $complaint->update([
                    'status' => ComplaintStatus::Pending,
                ]);
            }
        });

        $complaint->refresh()->load(['user', 'replier', 'messages.user']);

        if ($isStaff) {
            $this->notificationService->create(
                $complaint->user,
                'complaint_replied',
                'رد جديد على تذكرتك',
                "تم الرد على تذكرتك: {$complaint->subject}",
                [
                    'complaint_id' => $complaint->id,
                    'status' => $complaint->status->value,
                ],
                false
            );
        } else {
            $this->notificationService->notifyPlatformAdmins(
                'complaint_submitted',
                'رسالة جديدة على تذكرة',
                "أضاف {$user->name} رسالة على التذكرة: {$complaint->subject}",
                [
                    'complaint_id' => $complaint->id,
                    'category' => (string) $complaint->category,
                    'subject' => $complaint->subject,
                ],
                $user
            );
        }

        return $this->successResponse(
            new ComplaintResource($complaint),
            'تم إرسال الرسالة'
        );
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062 || $driverCode === 19;
    }
}
