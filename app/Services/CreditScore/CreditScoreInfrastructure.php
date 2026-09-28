<?php

namespace App\Services\CreditScore;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class CreditScoreInfrastructure
{
    public static function schemaReady(): bool
    {
        return Schema::hasTable('credit_score_model_versions')
            && Schema::hasTable('customer_credit_scores')
            && Schema::hasColumn('customers', 'current_credit_score_id');
    }

    public static function schemaErrorMessage(): string
    {
        return 'نظام التقييم الائتماني غير مُفعَّل على الخادم بعد. نفّذ php artisan migrate ثم php artisan credit-score:backfill.';
    }

    public static function isSchemaException(Throwable $e): bool
    {
        if ($e instanceof QueryException) {
            $message = $e->getMessage();

            return str_contains($message, 'customer_credit_scores')
                || str_contains($message, 'credit_score_model_versions')
                || str_contains($message, 'credit_score_audit_logs')
                || str_contains($message, 'current_credit_score_id')
                || str_contains($message, 'Base table or view not found');
        }

        $previous = $e->getPrevious();
        if ($previous instanceof Throwable) {
            return self::isSchemaException($previous);
        }

        return false;
    }

    public static function userFacingError(Throwable $e, bool $debug): string
    {
        if (self::isSchemaException($e)) {
            return self::schemaErrorMessage();
        }

        if ($debug) {
            return $e->getMessage() ?: 'حدث خطأ أثناء حساب التقييم الائتماني.';
        }

        return 'تعذر حساب التقييم الائتماني لهذا العميل. راجع سجل الخادم أو أعد المحاولة لاحقًا.';
    }
}
