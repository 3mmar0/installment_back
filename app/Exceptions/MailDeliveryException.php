<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class MailDeliveryException extends Exception
{
    public function __construct(
        string $message = 'تعذر إرسال البريد الإلكتروني، يرجى المحاولة لاحقاً',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
