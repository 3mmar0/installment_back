<?php

namespace App\Services\CreditScore\Support;

use Illuminate\Support\Arr;

/**
 * Immutable wrapper around a credit-score configuration array.
 *
 * The array is either the live config/credit_score.php (for the currently
 * active version) or the frozen `configuration` copied onto a published
 * `credit_score_model_versions` row. Analyzers only ever read through this
 * wrapper so there are no magic numbers scattered across the engine.
 */
final class CreditScoreConfig
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    public static function fromLiveConfig(): self
    {
        return new self((array) config('credit_score'));
    }

    public function get(string $path, mixed $default = null): mixed
    {
        return Arr::get($this->config, $path, $default);
    }

    public function float(string $path, float $default = 0.0): float
    {
        return (float) $this->get($path, $default);
    }

    public function int(string $path, int $default = 0): int
    {
        return (int) $this->get($path, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function array(string $path): array
    {
        $value = $this->get($path, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->config;
    }
}
