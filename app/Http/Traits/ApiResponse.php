<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    /**
     * Return a success JSON response.
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'تمت العملية بنجاح',
        int $statusCode = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
                return $data->additional($response)->response()->setStatusCode($statusCode);
            }
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return an error JSON response.
     */
    protected function errorResponse(
        string $message = 'حدث خطأ',
        int $statusCode = 400,
        ?array $errors = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a created response.
     */
    protected function createdResponse(
        mixed $data = null,
        string $message = 'تم إنشاء المورد بنجاح'
    ): JsonResponse {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Return a deleted response.
     */
    protected function deletedResponse(string $message = 'تم حذف المورد بنجاح'): JsonResponse
    {
        return $this->successResponse(null, $message, 200);
    }

    /**
     * Return a not found response.
     */
    protected function notFoundResponse(string $message = 'المورد غير موجود'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Return an unauthorized response.
     */
    protected function unauthorizedResponse(string $message = 'غير مصرح'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Return a forbidden response.
     */
    protected function forbiddenResponse(string $message = 'ممنوع الوصول'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Return a validation error response.
     */
    protected function validationErrorResponse(
        array $errors,
        string $message = 'فشلت عملية التحقق'
    ): JsonResponse {
        return $this->errorResponse($message, 422, $errors);
    }
}
