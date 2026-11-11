<?php

namespace App\Services\Assistant;

use App\Helpers\GovernorateHelper;
use App\Helpers\NationalIdHelper;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\InstallmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssistantToolRunner
{
    private ?string $sessionId = null;

    public function __construct(
        private readonly CustomerService $customerService,
        private readonly InstallmentService $installmentService,
        private readonly PendingActionStore $pendingActionStore,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function declarations(): array
    {
        return [
            [
                'name' => 'search_customers',
                'description' => 'Search this merchant\'s customers only by name/phone/national id/id. If count=0 ask to clarify. If count>1 return status=ambiguous and ask user to pick by id. Never invent customers.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search text (name, phone, national id, or numeric id)',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'list_customers',
                'description' => 'List this merchant\'s recent customers only. Use for «ورّيني العملاء». Empty list means no customers — say so honestly.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Optional search text',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Max rows to return (default 15, max 30)',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_customer',
                'description' => 'Get one owned customer by id. Returns error if missing or not owned. Sets selected customer context.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => ['type' => 'integer'],
                    ],
                    'required' => ['customer_id'],
                ],
            ],
            [
                'name' => 'summarize_customer_installments',
                'description' => 'Summarize installments for one owned customer. If multiple name matches, returns status=ambiguous instead of guessing.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => ['type' => 'integer'],
                        'customer_query' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'get_installment',
                'description' => 'Get one installment by id and prepare navigation to installment details.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'installment_id' => ['type' => 'integer'],
                    ],
                    'required' => ['installment_id'],
                ],
            ],
            [
                'name' => 'find_open_installment_item',
                'description' => 'Find the next unpaid or partial installment item for a customer or installment.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => ['type' => 'integer'],
                        'installment_id' => ['type' => 'integer'],
                        'customer_query' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'open_screen',
                'description' => 'Open a screen in the app (customers list, installment list, dashboard, detail pages, etc.).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'screen' => [
                            'type' => 'string',
                            'description' => 'One of: customers, customer_detail, installments, installment_detail, dashboard, overdue, due_soon, reports, notifications',
                        ],
                        'id' => [
                            'type' => 'integer',
                            'description' => 'Required for customer_detail and installment_detail',
                        ],
                    ],
                    'required' => ['screen'],
                ],
            ],
            [
                'name' => 'propose_create_customer',
                'description' => 'Propose creating a customer only. Does not save until the user confirms.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'national_id' => ['type' => 'string'],
                        'governorate' => ['type' => 'string', 'description' => 'Arabic or English governorate name, or code like cairo'],
                        'email' => ['type' => 'string'],
                        'address' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                        'job' => ['type' => 'string'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'propose_create_installment',
                'description' => 'Propose creating an installment for an EXISTING customer after an interactive chat gathered the fields. Call ONLY when you already have customer (id or clear unique name), total_amount, and months. Ask the user step-by-step first; do not call this on the first vague "create installment" message. Supports prepaid_months. Does not save until the user confirms in the app.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => ['type' => 'integer'],
                        'customer_query' => ['type' => 'string'],
                        'total_amount' => ['type' => 'number'],
                        'months' => ['type' => 'integer'],
                        'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD absolute date; omit when using start_months_ago'],
                        'start_months_ago' => [
                            'type' => 'integer',
                            'description' => 'Relative start: N months before today. REQUIRED for من تلت/ثلاث شهور / قبل شهرين. Never invent YYYY-MM-DD for those phrases. Do NOT treat months as years.',
                        ],
                        'name' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                        'prepaid_months' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => '1-based month numbers already paid, e.g. [1,2]',
                        ],
                    ],
                    'required' => ['total_amount', 'months'],
                ],
            ],
            [
                'name' => 'propose_create_customer_with_installment',
                'description' => 'Propose creating a NEW customer and an installment in one confirmation AFTER gathering customer+installment fields interactively in chat. Use when the user wants a new customer with an installment together. Do not call until name, total_amount, and months are known.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'national_id' => ['type' => 'string'],
                        'governorate' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                        'address' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                        'job' => ['type' => 'string'],
                        'total_amount' => ['type' => 'number'],
                        'months' => ['type' => 'integer'],
                        'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD absolute date; omit when using start_months_ago'],
                        'start_months_ago' => [
                            'type' => 'integer',
                            'description' => 'Relative start: N months before today. Use for من N شهور. Not years.',
                        ],
                        'installment_name' => ['type' => 'string'],
                        'installment_notes' => ['type' => 'string'],
                        'prepaid_months' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => '1-based month numbers already paid, e.g. [1,2]',
                        ],
                    ],
                    'required' => ['name', 'total_amount', 'months'],
                ],
            ],
            [
                'name' => 'propose_pay_installment_item',
                'description' => 'Propose paying an owned installment item (full or partial). Never marks paid until explicit app confirmation. If customer is ambiguous return status=ambiguous.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'item_id' => ['type' => 'integer'],
                        'customer_id' => ['type' => 'integer'],
                        'installment_id' => ['type' => 'integer'],
                        'customer_query' => ['type' => 'string'],
                        'paid_amount' => ['type' => 'number'],
                        'carry_remainder' => ['type' => 'boolean'],
                        'reference' => ['type' => 'string'],
                        'note' => ['type' => 'string'],
                    ],
                    'required' => ['paid_amount'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, pending_action?: array<string, mixed>|null, navigation?: array<string, mixed>|null}
     */
    public function run(string $name, array $args, User $user, ?string $sessionId = null): array
    {
        $this->sessionId = $sessionId;

        return match ($name) {
            'search_customers' => $this->searchCustomers($args, $user),
            'list_customers' => $this->listCustomers($args, $user),
            'get_customer' => $this->getCustomer($args, $user),
            'summarize_customer_installments' => $this->summarizeCustomerInstallments($args, $user),
            'get_installment' => $this->getInstallment($args, $user),
            'find_open_installment_item' => $this->findOpenItem($args, $user),
            'open_screen' => $this->openScreen($args, $user),
            'propose_create_customer' => $this->proposeCreateCustomer($args, $user),
            'propose_create_installment' => $this->proposeCreateInstallment($args, $user),
            'propose_create_customer_with_installment' => $this->proposeCreateCustomerWithInstallment($args, $user),
            'propose_pay_installment_item' => $this->proposePay($args, $user),
            default => ['ok' => false, 'error' => "Unknown tool: {$name}"],
        };
    }

    /**
     * @param  array<string, mixed>  $pending
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, navigation?: array<string, mixed>|null}
     */
    public function confirm(array $pending, User $user): array
    {
        if ((int) ($pending['user_id'] ?? 0) !== (int) $user->id) {
            return ['ok' => false, 'error' => 'هذا الإجراء غير مصرح لك.'];
        }

        $type = (string) ($pending['type'] ?? '');
        $payload = is_array($pending['payload'] ?? null) ? $pending['payload'] : [];

        try {
            return match ($type) {
                'create_customer' => $this->executeCreateCustomer($payload, $user),
                'create_installment' => $this->executeCreateInstallment($payload, $user),
                'create_customer_with_installment' => $this->executeCreateCustomerWithInstallment($payload, $user),
                'pay_installment_item' => $this->executePay($payload, $user),
                default => ['ok' => false, 'error' => 'نوع الإجراء غير معروف.'],
            };
        } catch (ValidationException $e) {
            $error = collect($e->errors())->flatten()->first() ?: 'بيانات غير صالحة.';
            Log::channel('assistant')->warning('assistant_confirm_validation_failed', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $error,
            ]);

            return [
                'ok' => false,
                'error' => $error,
            ];
        } catch (\Throwable $e) {
            Log::channel('assistant')->error('assistant_confirm_exception', [
                'user_id' => $user->id,
                'type' => $type,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return ['ok' => false, 'error' => $e->getMessage() ?: 'فشل تنفيذ الإجراء.'];
        }
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, result?: array<string, mixed>, error?: string}
     */
    private function searchCustomers(array $args, User $user): array
    {
        $query = trim((string) ($args['query'] ?? ''));
        if ($query === '') {
            return ['ok' => false, 'error' => 'اكتب اسم أو رقم للبحث.'];
        }

        $rows = $this->customerService->getCustomersForSelect($user, $query)
            ->take(8)
            ->map(fn (Customer $c) => $this->customerListRow($c))
            ->values()
            ->all();

        $status = match (true) {
            count($rows) === 0 => 'empty',
            count($rows) > 1 => 'ambiguous',
            default => 'ok',
        };

        return [
            'ok' => true,
            'result' => [
                'status' => $status,
                'count' => count($rows),
                'customers' => $rows,
                'ambiguous' => $status === 'ambiguous',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, navigation?: array<string, mixed>|null}
     */
    private function listCustomers(array $args, User $user): array
    {
        $limit = (int) ($args['limit'] ?? 15);
        $limit = max(1, min(30, $limit));
        $query = $this->nullableString($args['query'] ?? null);

        $rows = $this->customerService->getCustomersForSelect($user, $query)
            ->take($limit)
            ->map(fn (Customer $c) => $this->customerListRow($c))
            ->values()
            ->all();

        return [
            'ok' => true,
            'result' => [
                'status' => count($rows) === 0 ? 'empty' : 'ok',
                'count' => count($rows),
                'customers' => $rows,
            ],
            'navigation' => [
                'screen' => 'CustomerList',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, navigation?: array<string, mixed>|null}
     */
    private function getCustomer(array $args, User $user): array
    {
        $customer = $this->ownedCustomer((int) ($args['customer_id'] ?? 0), $user);
        if (! $customer) {
            return ['ok' => false, 'error' => 'العميل غير موجود.'];
        }

        return [
            'ok' => true,
            'result' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'national_id' => $customer->national_id,
                'governorate' => $customer->governorate,
                'governorate_label' => GovernorateHelper::label($customer->governorate),
                'address' => $customer->address,
            ],
            'navigation' => [
                'screen' => 'CustomerDetail',
                'params' => ['id' => $customer->id],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, navigation?: array<string, mixed>|null}
     */
    private function summarizeCustomerInstallments(array $args, User $user): array
    {
        $resolved = $this->resolveCustomer($args, $user);
        if (! ($resolved['ok'] ?? false)) {
            return $resolved;
        }

        /** @var Customer $customer */
        $customer = $resolved['customer'];

        $installments = Installment::query()
            ->with(['items' => fn ($q) => $q->orderBy('due_date')->orderBy('id')])
            ->where('customer_id', $customer->id)
            ->when(! $user->canManageMerchantData(), fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('id')
            ->get();

        $today = now()->startOfDay();
        $plans = [];
        $totalAmount = 0.0;
        $totalPaid = 0.0;
        $overdueCount = 0;

        foreach ($installments as $installment) {
            $planPaid = 0.0;
            $planRemaining = 0.0;
            $itemsSummary = [];

            foreach ($installment->items as $index => $item) {
                $due = round((float) $item->amount, 2);
                $paid = round((float) ($item->paid_amount ?? 0), 2);
                if ($item->status === 'paid') {
                    $paid = $due;
                }
                $remaining = max(0, round($due - $paid, 2));
                $planPaid += $paid;
                $planRemaining += $remaining;

                $isOverdue = $remaining > 0.001
                    && in_array($item->status, ['pending', 'partial', 'overdue'], true)
                    && $item->due_date
                    && $item->due_date->lt($today);
                if ($isOverdue) {
                    $overdueCount++;
                }

                $itemsSummary[] = [
                    'month' => $index + 1,
                    'item_id' => $item->id,
                    'due_date' => $item->due_date?->toDateString(),
                    'amount' => $due,
                    'paid_amount' => $paid,
                    'remaining' => $remaining,
                    'status' => $item->status,
                    'overdue' => $isOverdue,
                ];
            }

            $planTotal = round((float) $installment->total_amount, 2);
            $totalAmount += $planTotal;
            $totalPaid += $planPaid;

            $plans[] = [
                'id' => $installment->id,
                'name' => $installment->name,
                'status' => $installment->status,
                'total_amount' => $planTotal,
                'paid_amount' => round($planPaid, 2),
                'remaining_amount' => round($planRemaining, 2),
                'months' => (int) $installment->months,
                'start_date' => $installment->start_date?->toDateString() ?? (string) $installment->start_date,
                'items' => $itemsSummary,
            ];
        }

        return [
            'ok' => true,
            'result' => [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                ],
                'plans_count' => count($plans),
                'total_amount' => round($totalAmount, 2),
                'total_paid' => round($totalPaid, 2),
                'total_remaining' => round($totalAmount - $totalPaid, 2),
                'overdue_items_count' => $overdueCount,
                'plans' => $plans,
            ],
            'navigation' => [
                'screen' => 'CustomerDetail',
                'params' => ['id' => $customer->id],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, navigation?: array<string, mixed>|null}
     */
    private function getInstallment(array $args, User $user): array
    {
        $installment = $this->ownedInstallment((int) ($args['installment_id'] ?? 0), $user);
        if (! $installment) {
            return ['ok' => false, 'error' => 'القسط غير موجود.'];
        }

        return [
            'ok' => true,
            'result' => [
                'id' => $installment->id,
                'name' => $installment->name,
                'customer_id' => $installment->customer_id,
                'customer_name' => $installment->customer?->name,
                'total_amount' => (float) $installment->total_amount,
                'months' => (int) $installment->months,
                'status' => $installment->status,
            ],
            'navigation' => [
                'screen' => 'InstallmentDetail',
                'params' => ['id' => $installment->id],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, result?: array<string, mixed>, error?: string}
     */
    private function findOpenItem(array $args, User $user): array
    {
        $resolved = $this->resolveOpenItem($args, $user);
        if (! ($resolved['ok'] ?? false)) {
            return $resolved;
        }

        /** @var InstallmentItem $item */
        $item = $resolved['item'];
        $due = (float) $item->amount;
        $paid = (float) ($item->paid_amount ?? 0);

        return [
            'ok' => true,
            'result' => [
                'item_id' => $item->id,
                'installment_id' => $item->installment_id,
                'customer_id' => $item->installment?->customer_id,
                'customer_name' => $item->installment?->customer?->name,
                'due_date' => $item->due_date?->toDateString() ?? (string) $item->due_date,
                'amount' => $due,
                'paid_amount' => $paid,
                'remaining_amount' => max(0, round($due - $paid, 2)),
                'status' => $item->status,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, navigation?: array<string, mixed>|null}
     */
    private function openScreen(array $args, User $user): array
    {
        $screen = strtolower(trim((string) ($args['screen'] ?? '')));
        $id = (int) ($args['id'] ?? 0);

        return match ($screen) {
            'customers', 'customer_list' => [
                'ok' => true,
                'result' => ['opened' => 'CustomerList'],
                'navigation' => ['screen' => 'CustomerList'],
            ],
            'customer_detail', 'customer' => $this->openCustomerDetail($id, $user),
            'installments', 'installment_list' => [
                'ok' => true,
                'result' => ['opened' => 'InstallmentList'],
                'navigation' => ['screen' => 'InstallmentList'],
            ],
            'installment_detail', 'installment' => $this->openInstallmentDetail($id, $user),
            'dashboard', 'home' => [
                'ok' => true,
                'result' => ['opened' => 'Dashboard'],
                'navigation' => ['screen' => 'Dashboard'],
            ],
            'overdue' => [
                'ok' => true,
                'result' => ['opened' => 'Overdue'],
                'navigation' => ['screen' => 'Overdue'],
            ],
            'due_soon', 'duesoon' => [
                'ok' => true,
                'result' => ['opened' => 'DueSoon'],
                'navigation' => ['screen' => 'DueSoon'],
            ],
            'reports' => [
                'ok' => true,
                'result' => ['opened' => 'Reports'],
                'navigation' => ['screen' => 'Reports'],
            ],
            'notifications' => [
                'ok' => true,
                'result' => ['opened' => 'Notifications'],
                'navigation' => ['screen' => 'Notifications'],
            ],
            default => [
                'ok' => false,
                'error' => 'شاشة غير معروفة. استخدم: customers, customer_detail, installments, installment_detail, dashboard, overdue, due_soon, reports, notifications',
            ],
        };
    }

    /**
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, navigation?: array<string, mixed>|null}
     */
    private function openCustomerDetail(int $id, User $user): array
    {
        $customer = $this->ownedCustomer($id, $user);
        if (! $customer) {
            return ['ok' => false, 'error' => 'لازم رقم عميل صالح لفتح التفاصيل.'];
        }

        return [
            'ok' => true,
            'result' => ['opened' => 'CustomerDetail', 'id' => $customer->id],
            'navigation' => [
                'screen' => 'CustomerDetail',
                'params' => ['id' => $customer->id],
            ],
        ];
    }

    /**
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, navigation?: array<string, mixed>|null}
     */
    private function openInstallmentDetail(int $id, User $user): array
    {
        $installment = $this->ownedInstallment($id, $user);
        if (! $installment) {
            return ['ok' => false, 'error' => 'لازم رقم قسط صالح لفتح التفاصيل.'];
        }

        return [
            'ok' => true,
            'result' => ['opened' => 'InstallmentDetail', 'id' => $installment->id],
            'navigation' => [
                'screen' => 'InstallmentDetail',
                'params' => ['id' => $installment->id],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, pending_action?: array<string, mixed>|null}
     */
    private function proposeCreateCustomer(array $args, User $user): array
    {
        $built = $this->buildCustomerPayload($args);
        if (! ($built['ok'] ?? false)) {
            return $built;
        }

        /** @var array<string, mixed> $payload */
        $payload = $built['payload'];

        $summary = 'إضافة عميل: '.$payload['name'];
        if ($payload['phone']) {
            $summary .= ' — هاتف '.$payload['phone'];
        }
        if ($payload['governorate']) {
            $summary .= ' — '.GovernorateHelper::label($payload['governorate']);
        }

        $id = $this->storePending($user->id, [
            'type' => 'create_customer',
            'summary_ar' => $summary,
            'payload' => $payload,
            'payload_preview' => $this->customerPreview($payload),
        ]);

        return [
            'ok' => true,
            'result' => ['status' => 'awaiting_confirmation', 'pending' => true, 'pending_action_id' => $id],
            'pending_action' => [
                'id' => $id,
                'type' => 'create_customer',
                'summary_ar' => $summary,
                'payload_preview' => $this->customerPreview($payload),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, pending_action?: array<string, mixed>|null}
     */
    private function proposeCreateInstallment(array $args, User $user): array
    {
        $missing = $this->missingInstallmentFields($args, requireCustomer: true);
        if ($missing !== []) {
            return [
                'ok' => true,
                'result' => [
                    'status' => 'needs_input',
                    'interactive' => true,
                    'missing' => $missing,
                    'missing_fields' => $missing,
                    'next_question_ar' => $this->nextInstallmentQuestion($missing),
                ],
            ];
        }

        $customerId = (int) ($args['customer_id'] ?? 0);
        if ($customerId <= 0 && ! empty($args['customer_query'])) {
            $matches = $this->customerService
                ->getCustomersForSelect($user, (string) $args['customer_query'])
                ->take(5)
                ->values();
            if ($matches->count() === 0) {
                return [
                    'ok' => true,
                    'result' => [
                        'status' => 'empty',
                        'count' => 0,
                        'customers' => [],
                        'next_question_ar' => 'مفيش عميل مطابق. حدّد الاسم أكتر أو أنشئ العميل أولاً.',
                    ],
                ];
            }
            if ($matches->count() > 1) {
                return [
                    'ok' => true,
                    'result' => [
                        'status' => 'ambiguous',
                        'ambiguous' => true,
                        'count' => $matches->count(),
                        'customers' => $matches->map(fn (Customer $c) => [
                            'id' => $c->id,
                            'name' => $c->name,
                            'phone' => $c->phone,
                        ])->all(),
                        'next_question_ar' => 'في أكتر من عميل بنفس الاسم. أنهي واحد تقصد؟ اذكر الـ id أو الاسم بالكامل.',
                    ],
                ];
            }
            $customerId = (int) $matches->first()->id;
        }

        $customer = $this->ownedCustomer($customerId, $user);
        if (! $customer) {
            return [
                'ok' => true,
                'result' => [
                    'status' => 'needs_input',
                    'interactive' => true,
                    'missing' => ['customer'],
                    'missing_fields' => ['customer'],
                    'next_question_ar' => 'أنهي عميل؟ قول الاسم أو اختار من قائمة العملاء.',
                ],
            ];
        }

        $built = $this->buildInstallmentPayload($args, $customer->id);
        if (! ($built['ok'] ?? false)) {
            return $built;
        }

        /** @var array<string, mixed> $payload */
        $payload = $built['payload'];
        $prepaid = $payload['prepaid_months'];

        $startLabel = \Carbon\Carbon::parse((string) $payload['start_date'])
            ->timezone((string) (config('app.timezone') ?: 'UTC'))
            ->format('d-m-Y');
        $summary = sprintf(
            'قسط لـ %s بمبلغ %s على %d شهر (بداية %s)%s',
            $customer->name,
            number_format($payload['total_amount'], 2),
            $payload['months'],
            $startLabel,
            $prepaid !== [] ? ' — مدفوع مسبقاً: أشهر '.implode('، ', $prepaid) : ''
        );

        $preview = array_merge($payload, [
            'customer_name' => $customer->name,
            'prepaid_months_label' => $prepaid !== [] ? implode('، ', $prepaid) : null,
        ]);

        $id = $this->storePending($user->id, [
            'type' => 'create_installment',
            'summary_ar' => $summary,
            'payload' => $payload,
            'payload_preview' => $preview,
        ]);

        return [
            'ok' => true,
            'result' => ['status' => 'awaiting_confirmation', 'pending' => true, 'pending_action_id' => $id],
            'pending_action' => [
                'id' => $id,
                'type' => 'create_installment',
                'summary_ar' => $summary,
                'payload_preview' => $preview,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, pending_action?: array<string, mixed>|null}
     */
    private function proposeCreateCustomerWithInstallment(array $args, User $user): array
    {
        $customerBuilt = $this->buildCustomerPayload($args);
        if (! ($customerBuilt['ok'] ?? false)) {
            return $customerBuilt;
        }

        /** @var array<string, mixed> $customerPayload */
        $customerPayload = $customerBuilt['payload'];

        $installmentArgs = [
            'total_amount' => $args['total_amount'] ?? null,
            'months' => $args['months'] ?? null,
            'start_date' => $args['start_date'] ?? null,
            'start_months_ago' => $args['start_months_ago'] ?? null,
            'name' => $args['installment_name'] ?? null,
            'notes' => $args['installment_notes'] ?? ($args['notes'] ?? null),
            'prepaid_months' => $args['prepaid_months'] ?? [],
        ];

        // Placeholder customer_id for validation shape; real id assigned on confirm.
        $installmentBuilt = $this->buildInstallmentPayload($installmentArgs, 0, false);
        if (! ($installmentBuilt['ok'] ?? false)) {
            return $installmentBuilt;
        }

        /** @var array<string, mixed> $installmentPayload */
        $installmentPayload = $installmentBuilt['payload'];
        unset($installmentPayload['customer_id']);

        $prepaid = $installmentPayload['prepaid_months'];
        $summary = sprintf(
            'عميل جديد %s + قسط %s على %d شهر (بداية %s)%s',
            $customerPayload['name'],
            number_format($installmentPayload['total_amount'], 2),
            $installmentPayload['months'],
            $installmentPayload['start_date'],
            $prepaid !== [] ? ' — مدفوع مسبقاً: أشهر '.implode('، ', $prepaid) : ''
        );

        $payload = [
            'customer' => $customerPayload,
            'installment' => $installmentPayload,
        ];

        $preview = array_merge(
            $this->customerPreview($customerPayload),
            [
                'total_amount' => $installmentPayload['total_amount'],
                'months' => $installmentPayload['months'],
                'start_date' => $installmentPayload['start_date'],
                'prepaid_months' => $prepaid,
                'prepaid_months_label' => $prepaid !== [] ? implode('، ', $prepaid) : null,
                'installment_name' => $installmentPayload['name'],
            ]
        );

        $id = $this->storePending($user->id, [
            'type' => 'create_customer_with_installment',
            'summary_ar' => $summary,
            'payload' => $payload,
            'payload_preview' => $preview,
        ]);

        return [
            'ok' => true,
            'result' => ['status' => 'awaiting_confirmation', 'pending' => true, 'pending_action_id' => $id],
            'pending_action' => [
                'id' => $id,
                'type' => 'create_customer_with_installment',
                'summary_ar' => $summary,
                'payload_preview' => $preview,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, pending_action?: array<string, mixed>|null}
     */
    private function proposePay(array $args, User $user): array
    {
        $paidAmount = round((float) ($args['paid_amount'] ?? 0), 2);
        if ($paidAmount < 0.01) {
            return ['ok' => false, 'error' => 'حدد مبلغ الدفع.'];
        }

        $itemId = (int) ($args['item_id'] ?? 0);
        if ($itemId <= 0) {
            $resolved = $this->resolveOpenItem($args, $user);
            if (! ($resolved['ok'] ?? false)) {
                return $resolved;
            }
            /** @var InstallmentItem $item */
            $item = $resolved['item'];
        } else {
            $item = InstallmentItem::query()->with(['installment.customer'])->find($itemId);
            if (! $item || ! $this->ownsInstallment($item->installment, $user)) {
                return ['ok' => false, 'error' => 'قسط الشهر غير موجود.'];
            }
        }

        if ($item->status === 'paid') {
            return ['ok' => false, 'error' => 'القسط ده متسدد بالفعل.'];
        }

        $due = round((float) $item->amount, 2);
        $already = round((float) ($item->paid_amount ?? 0), 2);
        $remaining = round($due - $already, 2);
        if ($paidAmount > $remaining + 0.001) {
            return [
                'ok' => false,
                'error' => 'المبلغ أكبر من المتبقي ('.$remaining.').',
            ];
        }

        $carry = filter_var($args['carry_remainder'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $payload = [
            'item_id' => $item->id,
            'paid_amount' => $paidAmount,
            'carry_remainder' => $carry,
            'reference' => $this->nullableString($args['reference'] ?? null),
            'note' => $this->nullableString($args['note'] ?? null),
        ];

        $customerName = $item->installment?->customer?->name ?? 'عميل';
        $leftover = round($remaining - $paidAmount, 2);
        $summary = sprintf(
            'سداد %s لـ %s (متبقي قبل الدفع %s)%s',
            number_format($paidAmount, 2),
            $customerName,
            number_format($remaining, 2),
            $leftover > 0.001
                ? ($carry ? ' مع ترحيل الباقي للشهر التالي' : ' مع إبقاء الباقي على نفس الشهر')
                : ''
        );

        $preview = [
            'item_id' => $item->id,
            'installment_id' => $item->installment_id,
            'customer_name' => $customerName,
            'paid_amount' => $paidAmount,
            'remaining_before' => $remaining,
            'carry_remainder' => $carry,
        ];

        $id = $this->storePending($user->id, [
            'type' => 'pay_installment_item',
            'summary_ar' => $summary,
            'payload' => $payload,
            'payload_preview' => $preview,
        ]);

        return [
            'ok' => true,
            'result' => ['status' => 'awaiting_confirmation', 'pending' => true, 'pending_action_id' => $id],
            'pending_action' => [
                'id' => $id,
                'type' => 'pay_installment_item',
                'summary_ar' => $summary,
                'payload_preview' => $preview,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, result?: array<string, mixed>, navigation?: array<string, mixed>|null}
     */
    private function executeCreateCustomer(array $payload, User $user): array
    {
        $customer = DB::transaction(fn () => $this->customerService->createCustomer($payload, $user));

        return [
            'ok' => true,
            'result' => [
                'id' => $customer->id,
                'name' => $customer->name,
            ],
            'navigation' => [
                'screen' => 'CustomerDetail',
                'params' => ['id' => $customer->id],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, result?: array<string, mixed>, navigation?: array<string, mixed>|null}
     */
    private function executeCreateInstallment(array $payload, User $user): array
    {
        $customer = $this->ownedCustomer((int) ($payload['customer_id'] ?? 0), $user);
        if (! $customer) {
            return ['ok' => false, 'error' => 'العميل غير موجود أو مش تابع لحسابك.'];
        }

        $installment = DB::transaction(fn () => $this->installmentService->createInstallment($payload, $user));

        return [
            'ok' => true,
            'result' => [
                'id' => $installment->id,
                'customer_id' => $installment->customer_id,
                'total_amount' => (float) $installment->total_amount,
                'prepaid_months' => $payload['prepaid_months'] ?? [],
            ],
            'navigation' => [
                'screen' => 'InstallmentDetail',
                'params' => ['id' => $installment->id],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, result?: array<string, mixed>, navigation?: array<string, mixed>|null, error?: string}
     */
    private function executeCreateCustomerWithInstallment(array $payload, User $user): array
    {
        $customerPayload = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        $installmentPayload = is_array($payload['installment'] ?? null) ? $payload['installment'] : [];

        if ($customerPayload === [] || $installmentPayload === []) {
            return ['ok' => false, 'error' => 'بيانات العميل أو القسط ناقصة.'];
        }

        $result = DB::transaction(function () use ($customerPayload, $installmentPayload, $user) {
            $customer = $this->customerService->createCustomer($customerPayload, $user);
            $installmentPayload['customer_id'] = $customer->id;
            $installment = $this->installmentService->createInstallment($installmentPayload, $user);

            return compact('customer', 'installment');
        });

        /** @var Customer $customer */
        $customer = $result['customer'];
        /** @var Installment $installment */
        $installment = $result['installment'];

        return [
            'ok' => true,
            'result' => [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'installment_id' => $installment->id,
                'total_amount' => (float) $installment->total_amount,
                'prepaid_months' => $installmentPayload['prepaid_months'] ?? [],
            ],
            'navigation' => [
                'screen' => 'InstallmentDetail',
                'params' => ['id' => $installment->id],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, result?: array<string, mixed>, navigation?: array<string, mixed>|null, error?: string}
     */
    private function executePay(array $payload, User $user): array
    {
        return DB::transaction(function () use ($payload, $user) {
            $item = InstallmentItem::query()
                ->with('installment')
                ->lockForUpdate()
                ->find((int) ($payload['item_id'] ?? 0));

            if (! $item || ! $this->ownsInstallment($item->installment, $user)) {
                return ['ok' => false, 'error' => 'قسط الشهر غير موجود.'];
            }

            if ($item->status === 'paid') {
                return ['ok' => false, 'error' => 'القسط ده متسدد بالفعل.'];
            }

            $updated = $this->installmentService->markItemPaid($item, $payload, $user);

            return [
                'ok' => true,
                'result' => [
                    'item_id' => $updated->id,
                    'status' => $updated->status,
                    'paid_amount' => (float) ($updated->paid_amount ?? 0),
                ],
                'navigation' => [
                    'screen' => 'InstallmentDetail',
                    'params' => ['id' => $updated->installment_id],
                ],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, payload?: array<string, mixed>, error?: string}
     */
    private function buildCustomerPayload(array $args): array
    {
        $nationalRaw = $this->nullableString($args['national_id'] ?? null);
        $nationalId = $nationalRaw !== null ? NationalIdHelper::normalize($nationalRaw) : null;
        if ($nationalRaw !== null && ($nationalId === null || strlen($nationalId) !== NationalIdHelper::LENGTH)) {
            return [
                'ok' => false,
                'error' => 'الرقم القومي لازم يكون 14 رقم. لو مش متاح سيبه فاضي.',
            ];
        }

        $govRaw = $this->nullableString($args['governorate'] ?? null);
        $governorate = GovernorateHelper::normalize($govRaw);
        if ($govRaw !== null && $governorate === null) {
            return [
                'ok' => false,
                'error' => 'المحافظة غير معروفة. اكتب اسم محافظة مصرية زي القاهرة أو الجيزة.',
            ];
        }

        $payload = [
            'name' => trim((string) ($args['name'] ?? '')),
            'phone' => $this->nullableString($args['phone'] ?? null),
            'national_id' => $nationalId,
            'governorate' => $governorate,
            'email' => $this->nullableString($args['email'] ?? null),
            'address' => $this->nullableString($args['address'] ?? null),
            'notes' => $this->nullableString($args['notes'] ?? null),
            'job' => $this->nullableString($args['job'] ?? null),
        ];

        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'size:14'],
            'governorate' => GovernorateHelper::rule(),
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'job' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return [
                'ok' => false,
                'error' => $validator->errors()->first() ?: 'بيانات العميل غير مكتملة.',
            ];
        }

        return ['ok' => true, 'payload' => $payload];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return list<string>
     */
    private function missingInstallmentFields(array $args, bool $requireCustomer = true): array
    {
        $missing = [];
        if ($requireCustomer) {
            $customerId = (int) ($args['customer_id'] ?? 0);
            $customerQuery = trim((string) ($args['customer_query'] ?? ''));
            if ($customerId <= 0 && $customerQuery === '') {
                $missing[] = 'customer';
            }
        }

        $amount = (float) ($args['total_amount'] ?? 0);
        if ($amount <= 0) {
            $missing[] = 'total_amount';
        }

        $months = (int) ($args['months'] ?? 0);
        if ($months < 1) {
            $missing[] = 'months';
        }

        return $missing;
    }

    /**
     * @param  list<string>  $missing
     */
    private function nextInstallmentQuestion(array $missing): string
    {
        $first = $missing[0] ?? '';

        return match ($first) {
            'customer' => 'أنهي عميل للقسط؟ قول اسم العميل.',
            'total_amount' => 'المبلغ الإجمالي للقسط كام؟',
            'months' => 'عدد الأشهر كام؟',
            default => 'كمّل بيانات القسط الناقصة.',
        };
    }

    /**
     * Prefer relative months-ago from Arabic phrases; fall back to absolute date or today.
     *
     * @param  array<string, mixed>  $args
     */
    private function resolveStartDate(array $args): string
    {
        $tz = (string) (config('app.timezone') ?: 'UTC');
        $today = now($tz)->startOfDay();

        $monthsAgo = $args['start_months_ago'] ?? null;
        if ($monthsAgo !== null && $monthsAgo !== '') {
            $n = (int) $monthsAgo;
            if ($n < 0) {
                $n = abs($n);
            }
            if ($n > 120) {
                // Guard against mistaking months for years (e.g. 36 years).
                $n = min($n, 120);
            }

            return $today->copy()->subMonthsNoOverflow($n)->toDateString();
        }

        $absolute = $this->nullableString($args['start_date'] ?? null);
        if ($absolute) {
            try {
                return \Carbon\Carbon::parse($absolute, $tz)->timezone($tz)->toDateString();
            } catch (\Throwable) {
                // fall through to today
            }
        }

        return $today->toDateString();
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, payload?: array<string, mixed>, error?: string}
     */
    private function buildInstallmentPayload(array $args, int $customerId, bool $requireCustomer = true): array
    {
        $startDate = $this->resolveStartDate($args);
        $months = (int) ($args['months'] ?? 0);
        $prepaid = $this->normalizePrepaidMonths($args['prepaid_months'] ?? [], $months);
        if ($prepaid['error'] ?? null) {
            return ['ok' => false, 'error' => $prepaid['error']];
        }

        $payload = [
            'customer_id' => $customerId,
            'total_amount' => round((float) ($args['total_amount'] ?? 0), 2),
            'months' => $months,
            'start_date' => $startDate,
            'name' => $this->nullableString($args['name'] ?? null),
            'notes' => $this->nullableString($args['notes'] ?? null),
            'products' => [],
            'prepaid_months' => $prepaid['months'],
        ];

        $rules = [
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'months' => ['required', 'integer', 'min:1', 'max:120'],
            'start_date' => ['required', 'date'],
            'name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];

        if ($requireCustomer) {
            $rules['customer_id'] = ['required', 'integer', 'min:1'];
        }

        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            return [
                'ok' => false,
                'error' => $validator->errors()->first() ?: 'بيانات القسط غير مكتملة.',
            ];
        }

        return ['ok' => true, 'payload' => $payload];
    }

    /**
     * @param  mixed  $raw
     * @return array{months?: list<int>, error?: string}
     */
    private function normalizePrepaidMonths(mixed $raw, int $months): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return ['months' => []];
        }

        if (! is_array($raw)) {
            return ['error' => 'الشهور المدفوعة مسبقاً لازم تكون قائمة أرقام زي [1,2].'];
        }

        $normalized = [];
        foreach ($raw as $value) {
            $month = (int) $value;
            if ($month < 1 || ($months > 0 && $month > $months)) {
                return ['error' => 'رقم الشهر المدفوع مسبقاً لازم يكون بين 1 وعدد الشهور.'];
            }
            $normalized[] = $month;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return ['months' => $normalized];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function customerPreview(array $payload): array
    {
        return [
            'name' => $payload['name'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'national_id' => $payload['national_id'] ?? null,
            'governorate' => $payload['governorate'] ?? null,
            'governorate_label' => GovernorateHelper::label($payload['governorate'] ?? null),
            'address' => $payload['address'] ?? null,
            'email' => $payload['email'] ?? null,
            'job' => $payload['job'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerListRow(Customer $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'governorate' => $c->governorate,
            'governorate_label' => GovernorateHelper::label($c->governorate),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, customer?: Customer, error?: string, result?: array<string, mixed>}
     */
    private function resolveCustomer(array $args, User $user): array
    {
        $customerId = (int) ($args['customer_id'] ?? 0);
        if ($customerId <= 0 && ! empty($args['customer_query'])) {
            $matches = $this->customerService
                ->getCustomersForSelect($user, (string) $args['customer_query'])
                ->take(5)
                ->values();
            if ($matches->count() === 0) {
                return ['ok' => false, 'error' => 'مفيش عميل مطابق.'];
            }
            if ($matches->count() > 1) {
                return [
                    'ok' => true,
                    'result' => [
                        'status' => 'ambiguous',
                        'ambiguous' => true,
                        'count' => $matches->count(),
                        'customers' => $matches->map(fn (Customer $c) => [
                            'id' => $c->id,
                            'name' => $c->name,
                            'phone' => $c->phone,
                        ])->all(),
                    ],
                ];
            }
            $customerId = (int) $matches->first()->id;
        }

        $customer = $this->ownedCustomer($customerId, $user);
        if (! $customer) {
            return ['ok' => false, 'error' => 'حدد العميل بالاسم أو الرقم.'];
        }

        return ['ok' => true, 'customer' => $customer];
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function storePending(int $userId, array $action): string
    {
        if ($this->sessionId) {
            $action['session_id'] = $this->sessionId;
        }

        return $this->pendingActionStore->put($userId, $action);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, item?: InstallmentItem, error?: string, result?: array<string, mixed>}
     */
    private function resolveOpenItem(array $args, User $user): array
    {
        $installmentId = (int) ($args['installment_id'] ?? 0);
        $customerId = (int) ($args['customer_id'] ?? 0);

        if ($installmentId <= 0 && $customerId <= 0 && ! empty($args['customer_query'])) {
            $matches = $this->customerService
                ->getCustomersForSelect($user, (string) $args['customer_query'])
                ->take(5)
                ->values();
            if ($matches->count() === 0) {
                return ['ok' => false, 'error' => 'مفيش عميل مطابق للدفع.'];
            }
            if ($matches->count() > 1) {
                return [
                    'ok' => true,
                    'result' => [
                        'status' => 'ambiguous',
                        'ambiguous' => true,
                        'count' => $matches->count(),
                        'customers' => $matches->map(fn (Customer $c) => [
                            'id' => $c->id,
                            'name' => $c->name,
                            'phone' => $c->phone,
                        ])->all(),
                    ],
                ];
            }
            $customerId = (int) $matches->first()->id;
        }

        $query = InstallmentItem::query()
            ->with(['installment.customer'])
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereHas('installment', function ($builder) use ($user, $installmentId, $customerId) {
                if (! $user->canManageMerchantData()) {
                    $builder->where('user_id', $user->id);
                }
                if ($installmentId > 0) {
                    $builder->where('id', $installmentId);
                }
                if ($customerId > 0) {
                    $builder->where('customer_id', $customerId);
                }
            })
            ->orderBy('due_date')
            ->orderBy('id');

        if ($installmentId <= 0 && $customerId <= 0) {
            return ['ok' => false, 'error' => 'حدد العميل أو رقم القسط للدفع.'];
        }

        $item = $query->first();
        if (! $item) {
            return ['ok' => false, 'error' => 'مفيش قسط مفتوح للسداد.'];
        }

        return ['ok' => true, 'item' => $item];
    }

    private function ownedCustomer(int $id, User $user): ?Customer
    {
        if ($id <= 0) {
            return null;
        }

        $query = Customer::query()->whereKey($id);
        if (! $user->canManageMerchantData()) {
            $query->where('user_id', $user->id);
        }

        return $query->first();
    }

    private function ownedInstallment(int $id, User $user): ?Installment
    {
        if ($id <= 0) {
            return null;
        }

        $query = Installment::query()->with('customer')->whereKey($id);
        if (! $user->canManageMerchantData()) {
            $query->where('user_id', $user->id);
        }

        return $query->first();
    }

    private function ownsInstallment(?Installment $installment, User $user): bool
    {
        if (! $installment) {
            return false;
        }

        return $user->canManageMerchantData() || (int) $installment->user_id === (int) $user->id;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
