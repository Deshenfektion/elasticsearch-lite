<?php

declare(strict_types=1);

namespace EsLite\Console;

final class Output
{
    private const array COLOURS = [
        'reset' => "\033[0m",
        'bold' => "\033[1m",
        'dim' => "\033[2m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'cyan' => "\033[36m",
    ];

    public function __construct(private readonly bool $decorated = true)
    {
    }

    public function line(string $message = ''): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    public function title(string $message): void
    {
        $this->line($this->colour($message, 'bold'));
    }

    public function success(string $message): void
    {
        $this->line($this->colour('✓ ', 'green') . $message);
    }

    public function warning(string $message): void
    {
        $this->line($this->colour('! ', 'yellow') . $message);
    }

    public function error(string $message): void
    {
        fwrite(STDERR, $this->colour('✗ ', 'red') . $message . PHP_EOL);
    }

    public function detail(string $label, string $value): void
    {
        $this->line(sprintf('  %s %s', str_pad($label, 22, '.'), $value));
    }

    public function table(array $rows, array $headers = []): void
    {
        if ($rows === []) {
            return;
        }

        $widths = [];

        foreach ([$headers, ...$rows] as $row) {
            foreach (array_values($row) as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, mb_strlen((string) $cell));
            }
        }

        if ($headers !== []) {
            $this->line($this->colour($this->row($headers, $widths), 'bold'));
        }

        foreach ($rows as $row) {
            $this->line($this->row($row, $widths));
        }
    }

    public function json(array $payload): void
    {
        $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function row(array $row, array $widths): string
    {
        $cells = [];

        foreach (array_values($row) as $index => $cell) {
            $cells[] = str_pad((string) $cell, $widths[$index] ?? 0);
        }

        return '  ' . rtrim(implode('  ', $cells));
    }

    private function colour(string $message, string $colour): string
    {
        if (!$this->decorated) {
            return $message;
        }

        return (self::COLOURS[$colour] ?? '') . $message . self::COLOURS['reset'];
    }
}
