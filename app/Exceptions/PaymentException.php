<?php

namespace App\Exceptions;

use App\Enums\ErrorCodes;
use Exception;

class PaymentException extends Exception
{
    public function __construct(
        string $message,
        public readonly ErrorCodes $errorCode,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function alreadyPaid(): self
    {
        return new self(
            'تم تسجيل دفعة لهذا القسط بالفعل.',
            ErrorCodes::InstallmentItemAlreadyPaid,
        );
    }

    public static function amountMismatch(float $expected): self
    {
        return new self(
            'قيمة الدفعة يجب أن تساوي قيمة القسط المستحقة (' . number_format($expected, 2) . ' ج.م).',
            ErrorCodes::PaymentAmountMismatch,
        );
    }
}
