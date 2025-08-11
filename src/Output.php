<?php

namespace GroupScholar\CycleCoordinator;

class Output
{
    private bool $capture;
    private array $lines = [];

    public function __construct(bool $capture = false)
    {
        $this->capture = $capture;
    }

    public function line(string $message): void
    {
        $this->write($message);
    }

    public function info(string $message): void
    {
        $this->write("[INFO] {$message}");
    }

    public function success(string $message): void
    {
        $this->write("[OK] {$message}");
    }

    public function error(string $message): void
    {
        $this->write("[ERROR] {$message}");
    }

    public function table(array $headers, array $rows): void
    {
        $data = array_merge([$headers], $rows);
        $widths = array_fill(0, count($headers), 0);

        foreach ($data as $row) {
            foreach ($row as $index => $value) {
                $length = strlen((string) $value);
                if ($length > $widths[$index]) {
                    $widths[$index] = $length;
                }
            }
        }

        $this->write($this->formatRow($headers, $widths));
        $this->write($this->formatRow(array_map(fn ($w) => str_repeat('-', $w), $widths), $widths));

        foreach ($rows as $row) {
            $this->write($this->formatRow($row, $widths));
        }
    }

    public function lines(): array
    {
        return $this->lines;
    }

    private function formatRow(array $row, array $widths): string
    {
        $cells = [];
        foreach ($row as $index => $value) {
            $cells[] = str_pad((string) $value, $widths[$index]);
        }

        return implode(' | ', $cells);
    }

    private function write(string $message): void
    {
        if ($this->capture) {
            $this->lines[] = $message;
            return;
        }

        fwrite(STDOUT, $message . PHP_EOL);
    }
}
