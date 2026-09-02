<?php

namespace App\Http\Controllers\Api;

use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ComplaintResource;
use App\Http\Traits\ApiResponse;
use App\Models\Complaint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = Complaint::query()
            ->with(['user', 'replier'])
            ->latest();

        if (! $user->canManageComplaints()) {
            $query->where('user_id', $user->id);
        }

        $complaints = $query->get();

        return $this->successResponse(
            ComplaintResource::collection($complaints),
            'تم جلب الطلبات بنجاح'
        );
    }

    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->canManageComplaints()) {
            return $this->forbiddenResponse('لا يمكن لمدير النظام إرسال طلبات');
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', 'in:support,complaint,billing,other'],
            'message' => ['required', 'string', 'max:5000'],
        ], [
            'subject.required' => 'الموضوع مطلوب',
            'category.required' => 'نوع الطلب مطلوب',
            'message.required' => 'الرسالة مطلوبة',
        ]);

        $complaint = Complaint::create([
            'user_id' => $user->id,
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'message' => $validated['message'],
            'status' => ComplaintStatus::Pending,
        ]);

        $complaint->load(['user', 'replier']);

        return $this->createdResponse(
            new ComplaintResource($complaint),
            'تم إرسال طلبك بنجاح'
        );
    }

    public function show(int $id, Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $complaint = Complaint::with(['user', 'replier'])->find($id);

        if (! $complaint) {
            return $this->notFoundResponse('الطلب غير موجود');
        }

        if (! $user->canManageComplaints() && $complaint->user_id !== $user->id) {
            return $this->forbiddenResponse('غير مصرح بعرض هذا الطلب');
        }

        return $this->successResponse(
            new ComplaintResource($complaint),
            'تم جلب الطلب بنجاح'
        );
    }

    public function reply(int $id, Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $user->canManageComplaints()) {
            return $this->forbiddenResponse('غير مصرح بالرد على الطلبات');
        }

        $complaint = Complaint::with(['user', 'replier'])->find($id);

        if (! $complaint) {
            return $this->notFoundResponse('الطلب غير موجود');
        }

        $validated = $request->validate([
            'admin_reply' => ['required', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', 'in:replied,closed'],
        ], [
            'admin_reply.required' => 'نص الرد مطلوب',
        ]);

        $complaint->update([
            'admin_reply' => $validated['admin_reply'],
            'replied_by' => $user->id,
            'replied_at' => now(),
            'status' => $validated['status'] ?? ComplaintStatus::Replied->value,
        ]);

        $complaint->load(['user', 'replier']);

        return $this->successResponse(
            new ComplaintResource($complaint),
            'تم إرسال الرد بنجاح'
        );
    }
}
