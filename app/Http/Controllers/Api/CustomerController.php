<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\CustomerServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CustomerServiceInterface $customerService
    ) {}

    /**
     * Get all customers for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $customers = $this->customerService->getCustomersForUser($request->user());

        return $this->successResponse(
            CustomerResource::collection($customers)->response()->getData(true),
            'Customers retrieved successfully'
        );
    }

    /**
     * Create a new customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        /** @var \App\Models\User $authUser */
        // @phpstan-ignore-next-line
        $authUser = auth()->user();

        $customer = $this->customerService->createCustomer(
            $request->validated(),
            $authUser
        );

        return $this->createdResponse(
            new CustomerResource($customer),
            'Customer created successfully'
        );
    }

    /**
     * Get a specific customer.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $customer = $this->customerService->findCustomerById($id);

        if (!$customer) {
            return $this->notFoundResponse('Customer not found');
        }

        // Check authorization
        if (!$request->user()->isOwner() && $customer->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('You are not authorized to view this customer');
        }

        return $this->successResponse(
            new CustomerResource($customer),
            'Customer retrieved successfully'
        );
    }

    /**
     * Update a customer.
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        try {
            $customer = $this->customerService->updateCustomer($id, $data, $request->user());

            return $this->successResponse(
                new CustomerResource($customer),
                'Customer updated successfully'
            );
        } catch (\Exception $e) {
            if ($e->getCode() === 403) {
                return $this->forbiddenResponse($e->getMessage());
            }
            return $this->notFoundResponse('Customer not found');
        }
    }

    /**
     * Delete a customer.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        try {
            $this->customerService->deleteCustomer($id, $request->user());

            return $this->deletedResponse('Customer deleted successfully');
        } catch (\Exception $e) {
            if ($e->getCode() === 403) {
                return $this->forbiddenResponse($e->getMessage());
            }
            return $this->notFoundResponse('Customer not found');
        }
    }

    /**
     * Get customer statistics.
     */
    public function stats(int $id, Request $request): JsonResponse
    {
        $customer = $this->customerService->findCustomerById($id);

        if (!$customer) {
            return $this->notFoundResponse('Customer not found');
        }

        // Check authorization
        if (!$request->user()->isOwner() && $customer->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('You are not authorized to view this customer');
        }

        $stats = $this->customerService->getCustomerStats($customer);

        return $this->successResponse($stats, 'Customer statistics retrieved successfully');
    }
}
