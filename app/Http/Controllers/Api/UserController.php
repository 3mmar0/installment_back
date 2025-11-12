<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\UserServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UserServiceInterface $userService
    ) {}

    /**
     * Get all users (owner only).
     */
    public function index(): JsonResponse
    {
        $users = $this->userService->getAllUsers();

        return $this->successResponse(
            UserResource::collection($users)->response()->getData(true),
            'تم جلب المستخدمين بنجاح'
        );
    }

    /**
     * Create a new user (owner only).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['sometimes', 'in:owner,user'],
        ]);

        $user = $this->userService->createUser($data);

        return $this->createdResponse(
            new UserResource($user),
            'تم إنشاء المستخدم بنجاح'
        );
    }

    /**
     * Get a specific user.
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->findUserById($id);

        if (!$user) {
            return $this->notFoundResponse('المستخدم غير موجود');
        }

        return $this->successResponse(
            new UserResource($user),
            'تم جلب المستخدم بنجاح'
        );
    }

    /**
     * Update a user.
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,' . $id],
            'password' => ['sometimes', 'nullable', Password::defaults()],
            'role' => ['sometimes', 'in:owner,user'],
        ]);

        try {
            $user = $this->userService->updateUser($id, $data);

            return $this->successResponse(
                new UserResource($user),
                'تم تحديث المستخدم بنجاح'
            );
        } catch (\Exception $e) {
            return $this->notFoundResponse('المستخدم غير موجود');
        }
    }

    /**
     * Delete a user.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->userService->deleteUser($id);

            return $this->deletedResponse('تم حذف المستخدم بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }
}
