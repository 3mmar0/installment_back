<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();

        if (!$row || $row->value === null || $row->value === '') {
            return $default;
        }

        $decoded = json_decode($row->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $row->value;
    }

    public static function set(string $key, mixed $value): void
    {
        $stored = is_array($value) || is_bool($value) || is_int($value) || is_float($value)
            ? json_encode($value)
            : (string) $value;

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored]
        );
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::get($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int) static::get($key, $default);
    }
}
