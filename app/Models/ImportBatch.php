<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PREVIEWED = 'previewed';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'type',
        'file_path',
        'original_name',
        'status',
        'total_rows',
        'processed_rows',
        'imported_count',
        'failed_count',
        'created_customers',
        'matched_customers',
        'report',
        'error',
    ];

    protected $casts = [
        'report' => 'array',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'imported_count' => 'integer',
        'failed_count' => 'integer',
        'created_customers' => 'integer',
        'matched_customers' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Progress percentage (0-100) of processed rows.
     */
    public function percent(): int
    {
        if ($this->total_rows <= 0) {
            return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true) ? 100 : 0;
        }

        return (int) min(100, round(($this->processed_rows / $this->total_rows) * 100));
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }
}
