<?php

declare(strict_types=1);

namespace EsLite\Bench;

final class Latency
{
    private array $samples = [];

    public function __construct(public readonly string $name)
    {
    }

    public function record(int $micros): void
    {
        $this->samples[] = $micros;
    }

    public function count(): int
    {
        return count($this->samples);
    }

    public function mean(): float
    {
        return $this->samples === [] ? 0.0 : array_sum($this->samples) / count($this->samples);
    }

    public function percentile(float $percentile): float
    {
        if ($this->samples === []) {
            return 0.0;
        }

        $sorted = $this->samples;
        sort($sorted);
        $rank = ($percentile / 100) * (count($sorted) - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);

        if ($low === $high) {
            return (float) $sorted[$low];
        }

        return $sorted[$low] + ($sorted[$high] - $sorted[$low]) * ($rank - $low);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'samples' => $this->count(),
            'mean_ms' => $this->millis($this->mean()),
            'p50_ms' => $this->millis($this->percentile(50)),
            'p90_ms' => $this->millis($this->percentile(90)),
            'p99_ms' => $this->millis($this->percentile(99)),
            'max_ms' => $this->millis($this->samples === [] ? 0 : max($this->samples)),
        ];
    }

    private function millis(float $micros): float
    {
        return round($micros / 1000, 3);
    }
}
