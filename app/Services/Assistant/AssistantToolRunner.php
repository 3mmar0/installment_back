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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssistantToolRunner
{
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
                'description' => 'Search vendor customers by name, phone, national id, or id.',
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
                'name' => 'get_customer',
                'description' => 'Get one customer by id and prepare navigation to customer details.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => ['type' => 'integer'],
                    ],
                    'required' => ['customer_id'],
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
                'name' => 'propose_create_customer',
                'description' => 'Propose creating a customer. Does not save until the user confirms.',
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
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'propose_create_installment',
                'description' => 'Propose creating an installment plan. Does not save until the user confirms.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => ['type' => 'integer'],
                        'customer_query' => ['type' => 'string'],
                        'total_amount' => ['type' => 'number'],
                        'months' => ['type' => 'integer'],
                        'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD; default today if omitted'],
                        'name' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                    ],
                    'required' => ['total_amount', 'months'],
                ],
            ],
            [
                'name' => 'propose_pay_installment_item',
                'description' => 'Propose paying an installment item (full or partial). Does not save until confirm.',
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
    public function run(string $name, array $args, User $user): array
    {
        return match ($name) {
            'search_customers' => $this->searchCustomers($args, $user),
            'get_customer' => $this->getCustomer($args, $user),
            'get_installment' => $this->getInstallment($args, $user),
            'find_open_installment_item' => $this->findOpenItem($args, $user),
            'propose_create_customer' => $this->proposeCreateCustomer($args, $user),
            'propose_create_installment' => $this->proposeCreateInstallment($args, $user),
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
                'pay_installment_item' => $this->executePay($payload, $user),
                default => ['ok' => false, 'error' => 'نوع الإجراء غير معروف.'],
            };
        } catch (ValidationException $e) {
            return [
                'ok' => false,
                'error' => collect($e->errors())->flatten()->first() ?: 'بيانات غير صالحة.',
            ];
        } catch (\Throwable $e) {
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
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'governorate' => $c->governorate,
            ])
            ->values()
            ->all();

        return [
            'ok' => true,
            'result' => [
                'count' => count($rows),
                'customers' => $rows,
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
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, pending_action?: array<string, mixed>|null}
     */
    private function proposeCreateCustomer(array $args, User $user): array
    {
        $payload = [
            'name' => trim((string) ($args['name'] ?? '')),
            'phone' => $this->nullableString($args['phone'] ?? null),
            'national_id' => $this->nullableString($args['national_id'] ?? null),
            'governorate' => GovernorateHelper::normalize($args['governorate'] ?? null),
            'email' => $this->nullableString($args['email'] ?? null),
            'address' => $this->nullableString($args['address'] ?? null),
            'notes' => $this->nullableString($args['notes'] ?? null),
        ];

        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'size:14'],
            'governorate' => GovernorateHelper::rule(),
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return [
                'ok' => false,
                'error' => $validator->errors()->first() ?: 'بيانات العميل غير مكتملة.',
            ];
        }

        if ($payload['national_id']) {
            $payload['national_id'] = NationalIdHelper::normalize($payload['national_id']);
        }

        $summary = 'إضافة عميل: '.$payload['name'];
        if ($payload['phone']) {
            $summary .= ' — هاتف '.$payload['phone'];
        }

        $id = $this->pendingActionStore->put($user->id, [
            'type' => 'create_customer',
            'summary_ar' => $summary,
            'payload' => $payload,
            'payload_preview' => $payload,
        ]);

        return [
            'ok' => true,
            'result' => ['pending' => true, 'pending_action_id' => $id],
            'pending_action' => [
                'id' => $id,
                'type' => 'create_customer',
                'summary_ar' => $summary,
                'payload_preview' => $payload,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, result?: array<string, mixed>, error?: string, pending_action?: array<string, mixed>|null}
     */
    private function proposeCreateInstallment(array $args, User $user): array
    {
        $customerId = (int) ($args['customer_id'] ?? 0);
        if ($customerId <= 0 && ! empty($args['customer_query'])) {
            $matches = $this->customerService
                ->getCustomersForSelect($user, (string) $args['customer_query'])
                ->take(5)
                ->values();
            if ($matches->count() === 0) {
                return ['ok' => false, 'error' => 'مفيش عميل مطابق. حدّد الاسم أو أنشئ العميل أولاً.'];
            }
            if ($matches->count() > 1) {
                return [
                    'ok' => true,
                    'result' => [
                        'ambiguous' => true,
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
            return ['ok' => false, 'error' => 'لازم تحدد عميل صالح قبل إنشاء القسط.'];
        }

        $startDate = $this->nullableString($args['start_date'] ?? null) ?: now()->toDateString();
        $payload = [
            'customer_id' => $customer->id,
            'total_amount' => round((float) ($args['total_amount'] ?? 0), 2),
            'months' => (int) ($args['months'] ?? 0),
            'start_date' => $startDate,
            'name' => $this->nullableString($args['name'] ?? null),
            'notes' => $this->nullableString($args['notes'] ?? null),
            'products' => [],
        ];

        $validator = Validator::make($payload, [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(function ($query) use ($user) {
                    if (! $user->canManageMerchantData()) {
                        $query->where('user_id', $user->id);
                    }
                }),
            ],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'months' => ['required', 'integer', 'min:1', 'max:120'],
            'start_date' => ['required', 'date'],
            'name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return [
                'ok' => false,
                'error' => $validator->errors()->first() ?: 'بيانات القسط غير مكتملة.',
            ];
        }

        $summary = sprintf(
            'قسط لـ %s بمبلغ %s على %d شهر (بداية %s)',
            $customer->name,
            number_format($payload['total_amount'], 2),
            $payload['months'],
            $payload['start_date']
        );

        $id = $this->pendingActionStore->put($user->id, [
            'type' => 'create_installment',
            'summary_ar' => $summary,
            'payload' => $payload,
            'payload_preview' => array_merge($payload, [
                'customer_name' => $customer->name,
            ]),
        ]);

        return [
            'ok' => true,
            'result' => ['pending' => true, 'pending_action_id' => $id],
            'pending_action' => [
                'id' => $id,
                'type' => 'create_installment',
                'summary_ar' => $summary,
                'payload_preview' => array_merge($payload, [
                    'customer_name' => $customer->name,
                ]),
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

        $id = $this->pendingActionStore->put($user->id, [
            'type' => 'pay_installment_item',
            'summary_ar' => $summary,
            'payload' => $payload,
            'payload_preview' => [
                'item_id' => $item->id,
                'installment_id' => $item->installment_id,
                'customer_name' => $customerName,
                'paid_amount' => $paidAmount,
                'remaining_before' => $remaining,
                'carry_remainder' => $carry,
            ],
        ]);

        return [
            'ok' => true,
            'result' => ['pending' => true, 'pending_action_id' => $id],
            'pending_action' => [
                'id' => $id,
                'type' => 'pay_installment_item',
                'summary_ar' => $summary,
                'payload_preview' => [
                    'item_id' => $item->id,
                    'installment_id' => $item->installment_id,
                    'customer_name' => $customerName,
                    'paid_amount' => $paidAmount,
                    'remaining_before' => $remaining,
                    'carry_remainder' => $carry,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, result?: array<string, mixed>, navigation?: array<string, mixed>|null}
     */
    private function executeCreateCustomer(array $payload, User $user): array
    {
        $customer = $this->customerService->createCustomer($payload, $user);

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
        $installment = $this->installmentService->createInstallment($payload, $user);

        return [
            'ok' => true,
            'result' => [
                'id' => $installment->id,
                'customer_id' => $installment->customer_id,
                'total_amount' => (float) $installment->total_amount,
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
        $item = InstallmentItem::query()->with('installment')->find((int) ($payload['item_id'] ?? 0));
        if (! $item || ! $this->ownsInstallment($item->installment, $user)) {
            return ['ok' => false, 'error' => 'قسط الشهر غير موجود.'];
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
                        'ambiguous' => true,
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
