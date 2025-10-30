<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DocsController extends Controller
{
    public function index(): JsonResponse
    {
        $docs = [
            'title' => 'Installment Program API',
            'version' => '1.0.0',
            'authentication' => 'Bearer token via Sanctum. Use Authorization: Bearer {token}',
            'endpoints' => [
                [
                    'name' => 'Auth - Register',
                    'method' => 'POST',
                    'path' => '/api/auth/register',
                    'auth' => false,
                    'request' => [
                        'name' => 'string (required)',
                        'email' => 'string email (required)',
                        'password' => 'string (required, confirmed)',
                        'password_confirmation' => 'string (required)',
                        'plan_id' => 'integer (optional, existing plans.id)',
                        'free_trial' => 'boolean (optional)'
                    ],
                    'response_201' => [
                        'success' => true,
                        'message' => 'Registration successful',
                        'data' => [
                            'user' => [
                                'id' => 'int',
                                'name' => 'string',
                                'email' => 'string',
                                'role' => 'owner|user',
                                'current_subscription' => [
                                    'id' => 'int|null',
                                    'status' => 'active|canceled|expired|past_due|null',
                                    'plan' => [
                                        'id' => 'int',
                                        'name' => 'string',
                                        'interval' => 'monthly|yearly'
                                    ]
                                ]
                            ],
                            'token' => 'string',
                            'token_type' => 'Bearer'
                        ]
                    ]
                ],
                [
                    'name' => 'Auth - Login',
                    'method' => 'POST',
                    'path' => '/api/auth/login',
                    'auth' => false,
                    'request' => [
                        'email' => 'string email (required)',
                        'password' => 'string (required)'
                    ],
                    'response_200' => [
                        'success' => true,
                        'message' => 'Login successful',
                        'data' => [
                            'user' => 'User object including current_subscription',
                            'token' => 'string',
                            'token_type' => 'Bearer'
                        ]
                    ]
                ],
                [
                    'name' => 'Plans - Public list',
                    'method' => 'GET',
                    'path' => '/api/plans',
                    'auth' => false,
                    'response_200' => [
                        'success' => true,
                        'message' => 'Plans retrieved successfully',
                        'data' => [
                            [
                                'id' => 'int',
                                'name' => 'string',
                                'price_cents' => 'int',
                                'currency' => 'USD',
                                'interval' => 'monthly|yearly',
                                'trial_days' => 'int|null',
                                'features' => 'object',
                                'is_active' => 'bool'
                            ]
                        ]
                    ]
                ],
                [
                    'name' => 'Plans - Admin list',
                    'method' => 'GET',
                    'path' => '/api/plans/admin',
                    'auth' => true,
                    'roles' => ['owner'],
                    'response_200' => 'Same as public list, includes inactive'
                ],
                [
                    'name' => 'Plans - Create',
                    'method' => 'POST',
                    'path' => '/api/plans',
                    'auth' => true,
                    'roles' => ['owner'],
                    'request' => [
                        'name' => 'string required',
                        'price_cents' => 'int required >=0',
                        'currency' => 'string 3-char required',
                        'interval' => 'in:monthly,yearly required',
                        'trial_days' => 'int nullable',
                        'features' => 'object nullable',
                        'is_active' => 'bool optional'
                    ],
                    'response_201' => [
                        'success' => true,
                        'message' => 'Plan created successfully',
                        'data' => 'Plan object'
                    ]
                ],
                [
                    'name' => 'Plans - Update',
                    'method' => 'PUT',
                    'path' => '/api/plans/{plan}',
                    'auth' => true,
                    'roles' => ['owner'],
                    'request' => 'Any Plan fields (partial)',
                    'response_200' => [
                        'success' => true,
                        'message' => 'Plan updated successfully',
                        'data' => 'Plan object'
                    ]
                ],
                [
                    'name' => 'Plans - Delete',
                    'method' => 'DELETE',
                    'path' => '/api/plans/{plan}',
                    'auth' => true,
                    'roles' => ['owner'],
                    'response_200' => [
                        'success' => true,
                        'message' => 'Plan deleted successfully'
                    ]
                ],
                [
                    'name' => 'Subscriptions - Current',
                    'method' => 'GET',
                    'path' => '/api/subscriptions/current',
                    'auth' => true,
                    'response_200' => [
                        'success' => true,
                        'message' => 'Current subscription retrieved',
                        'data' => [
                            'id' => 'int',
                            'status' => 'string',
                            'starts_at' => 'iso',
                            'ends_at' => 'iso',
                            'next_due_at' => 'iso',
                            'amount_cents' => 'int',
                            'paid_cents' => 'int',
                            'plan' => ['id' => 'int', 'name' => 'string', 'interval' => 'string']
                        ]
                    ]
                ],
                [
                    'name' => 'Subscriptions - Subscribe',
                    'method' => 'POST',
                    'path' => '/api/subscriptions/subscribe',
                    'auth' => true,
                    'request' => [
                        'plan_id' => 'int required exists:plans,id'
                    ],
                    'response_201' => [
                        'success' => true,
                        'message' => 'Subscribed successfully',
                        'data' => 'Subscription object with plan'
                    ]
                ],
                [
                    'name' => 'Subscriptions - Cancel',
                    'method' => 'POST',
                    'path' => '/api/subscriptions/cancel',
                    'auth' => true,
                    'response_200' => [
                        'success' => true,
                        'message' => 'Subscription canceled',
                        'data' => 'Subscription object'
                    ]
                ],
                [
                    'name' => 'Subscriptions - Change Plan',
                    'method' => 'POST',
                    'path' => '/api/subscriptions/change-plan',
                    'auth' => true,
                    'request' => [
                        'plan_id' => 'int required exists:plans,id'
                    ],
                    'response_200' => [
                        'success' => true,
                        'message' => 'Plan changed successfully',
                        'data' => 'Subscription object with plan'
                    ]
                ],
                [
                    'name' => 'Subscriptions - Payments List',
                    'method' => 'GET',
                    'path' => '/api/subscriptions/payments',
                    'auth' => true,
                    'response_200' => [
                        'success' => true,
                        'message' => 'Payments retrieved',
                        'data' => [
                            [
                                'id' => 'int',
                                'amount_cents' => 'int',
                                'type' => 'payment|refund|adjustment',
                                'note' => 'string|null',
                                'created_at' => 'iso'
                            ]
                        ]
                    ]
                ],
                [
                    'name' => 'Subscriptions - Record Payment',
                    'method' => 'POST',
                    'path' => '/api/subscriptions/record-payment',
                    'auth' => true,
                    'request' => [
                        'amount_cents' => 'int required >=1',
                        'note' => 'string optional'
                    ],
                    'response_200' => [
                        'success' => true,
                        'message' => 'Payment recorded',
                        'data' => [
                            'transaction' => 'Transaction object',
                            'subscription' => 'Updated subscription object'
                        ]
                    ]
                ],
                [
                    'name' => 'Users - Owner List',
                    'method' => 'GET',
                    'path' => '/api/user-list',
                    'auth' => true,
                    'roles' => ['owner'],
                    'response_200' => [
                        'success' => true,
                        'message' => 'Users retrieved successfully',
                        'data' => [
                            'data' => [
                                '... users with current_subscription.plan.name etc ...'
                            ]
                        ]
                    ]
                ],
                [
                    'name' => 'Users - Owner Show',
                    'method' => 'GET',
                    'path' => '/api/user-show/{id}',
                    'auth' => true,
                    'roles' => ['owner'],
                    'response_200' => [
                        'success' => true,
                        'message' => 'User retrieved successfully',
                        'data' => 'User object including current_subscription and plan'
                    ]
                ],
            ],
        ];

        return response()->json($docs);
    }
}
