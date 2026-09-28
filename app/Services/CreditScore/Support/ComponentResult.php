<?php

namespace App\Services\CreditScore\Support;

/**
 * The output of a single scoring component.
 *
 * `score` is 0..100 when the component could be evaluated, or null when the
 * component is unavailable for this customer (e.g. no due installments yet).
 * Unavailable components are excluded from the weighted average and their
 * weight is redistributed across the rest.
 */
final class ComponentResult
{
    /**
     * @param  array<string, mixed>  $metrics
     */
    public function __construct(
        public readonly ?float $score,
        public readonly array $metrics = [],
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->score !== null;
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public static function score(float $score, array $metrics = []): self
    {
        return new self($score, $metrics);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public static function unavailable(array $metrics = []): self
    {
        return new self(null, $metrics);
    }
}
