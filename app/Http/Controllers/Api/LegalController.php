<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class LegalController extends Controller
{
    use ApiResponse;

    public function privacy(): JsonResponse
    {
        return $this->successResponse(config('legal.privacy'), 'سياسة الخصوصية');
    }

    public function terms(): JsonResponse
    {
        return $this->successResponse(config('legal.terms'), 'الشروط والأحكام');
    }
}
