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
            'version' => '2.0.0',
            'authentication' => 'Bearer token via Sanctum. Use Authorization: Bearer {token}',
            'sections' => [
                [
                    'name' => 'Authentication',
                    'endpoints' => [
                        [
                    'method' => 'POST',
                    'path' => '/api/auth/register',
                    'auth' => false,
                            'description' => 'Create a new account. Optionally provide a subscription_id to immediately assign a plan.',
                    'request' => [
                                'name' => 'string required',
                                'email' => 'string email required',
                                'password' => 'string required (confirmed)',
                                'password_confirmation' => 'string required',
                                'subscription_id' => 'integer optional (existing subscriptions.id)',
                            ],
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/auth/login',
                    'auth' => false,
                            'description' => 'Authenticate user and issue Sanctum token.',
                    'request' => [
                                'email' => 'string email required',
                                'password' => 'string required',
                    ],
                        ],
                        [
                            'method' => 'GET',
                            'path' => '/api/auth/me',
                            'auth' => true,
                            'description' => 'Retrieve the authenticated profile including current user limit snapshot.',
                        ],
                    ],
                ],
                [
                    'name' => 'Subscription Plans',
                    'endpoints' => [
                        [
                    'method' => 'GET',
                            'path' => '/api/subscriptions',
                    'auth' => false,
                            'description' => 'Public list of active subscription plans.',
                ],
                [
                    'method' => 'GET',
                            'path' => '/api/subscriptions/admin',
                    'auth' => true,
                    'roles' => ['owner'],
                            'description' => 'Owner view of all plans (active and inactive) with pagination.',
                ],
                [
                    'method' => 'POST',
                            'path' => '/api/subscriptions',
                    'auth' => true,
                    'roles' => ['owner'],
                            'description' => 'Create a new subscription plan.',
                    'request' => [
                        'name' => 'string required',
                                'slug' => 'string optional unique',
                                'price' => 'numeric required >= 0',
                                'currency' => 'string optional default EGP',
                                'duration' => 'in:monthly,yearly required',
                                'customers' => 'object optional {from,to}',
                                'installments' => 'object optional {from,to}',
                                'notifications' => 'object optional {from,to}',
                                'reports' => 'boolean optional',
                                'features' => 'object optional',
                                'is_active' => 'boolean optional',
                            ],
                        ],
                        [
                    'method' => 'PUT',
                            'path' => '/api/subscriptions/{subscription}',
                    'auth' => true,
                    'roles' => ['owner'],
                            'description' => 'Update plan details.',
                ],
                [
                    'method' => 'DELETE',
                            'path' => '/api/subscriptions/{subscription}',
                    'auth' => true,
                    'roles' => ['owner'],
                            'description' => 'Soft-remove a subscription plan.',
                ],
                [
                    'method' => 'POST',
                            'path' => '/api/subscriptions/{subscription}/assign',
                    'auth' => true,
                            'roles' => ['owner'],
                            'description' => 'Assign a plan to a specific user and sync their usage limits.',
                    'request' => [
                                'user_id' => 'integer required',
                                'start_date' => 'date optional',
                                'end_date' => 'date optional >= start_date',
                                'status' => 'in:active,paused,canceled optional',
                                'features' => 'object optional overrides',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'User Limits',
                    'endpoints' => [
                        [
                            'method' => 'GET',
                            'path' => '/api/limits/current',
                    'auth' => true,
                            'description' => 'Current user subscription snapshot (limits, usage, remaining).',
                ],
                [
                    'method' => 'POST',
                            'path' => '/api/limits/refresh',
                    'auth' => true,
                            'description' => 'Recalculate usage counters for the authenticated user.',
                        ],
                        [
                    'method' => 'GET',
                            'path' => '/api/limits/can-create/{resource}',
                    'auth' => true,
                            'description' => 'Check whether the authenticated user can create another resource (customers|installments|notifications).',
                        ],
                        [
                            'method' => 'POST',
                            'path' => '/api/limits/increment/{resource}',
                            'auth' => true,
                            'description' => 'Increment usage counter for the specified resource.',
                        ],
                        [
                    'method' => 'POST',
                            'path' => '/api/limits/decrement/{resource}',
                    'auth' => true,
                            'description' => 'Decrement usage counter for the specified resource.',
                        ],
                        [
                            'method' => 'GET',
                            'path' => '/api/limits/feature/{feature}',
                            'auth' => true,
                            'description' => 'Check whether a feature flag is enabled for the authenticated user plan.',
                        ],
                        [
                    'method' => 'GET',
                            'path' => '/api/limits',
                            'auth' => true,
                            'roles' => ['owner'],
                            'description' => 'Owner view of all user limit profiles (paginated).',
                        ],
                        [
                            'method' => 'POST',
                            'path' => '/api/limits',
                    'auth' => true,
                    'roles' => ['owner'],
                            'description' => 'Owner ability to create or override user limit profile manually.',
                        ],
                    ],
                ],
                [
                    'name' => 'Core Resources (require active subscription)',
                    'description' => 'Customers, installments, and notifications endpoints remain unchanged but are now guarded by the updated EnsureActiveSubscription middleware that reads user limits.',
                ],
            ],
        ];

        return response()->json($docs);
    }
}
