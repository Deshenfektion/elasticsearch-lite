<?php

declare(strict_types=1);

namespace EsLite\Support;

final class Stopwatch
{
    private float $startedAt;

    private array $marks = [];

    public function __construct(private readonly Clock $clock = new SystemClock())
    {
        $this->startedAt = $this->clock->monotonic();
    }

    public function mark(string $name): void
    {
        $this->marks[$name] = $this->elapsedMicros();
    }

    public function marks(): array
    {
        return $this->marks;
    }

    public function elapsedMicros(): int
    {
        return (int) round(($this->clock->monotonic() - $this->startedAt) * 1_000_000);
    }

    public function elapsedMillis(): float
    {
        return round($this->elapsedMicros() / 1000, 3);
    }

    public function reset(): void
    {
        $this->startedAt = $this->clock->monotonic();
        $this->marks = [];
    }
}
