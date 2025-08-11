<?php

namespace GroupScholar\CycleCoordinator\Tests;

require __DIR__ . '/../src/App.php';
require __DIR__ . '/../src/CycleStore.php';
require __DIR__ . '/../src/Output.php';
require __DIR__ . '/MemoryCycleStore.php';

use GroupScholar\CycleCoordinator\App;
use GroupScholar\CycleCoordinator\Output;

class AppTest
{
    public function run(): void
    {
        $this->testSeedAndList();
        $this->testAddCycle();
        $this->testUpdateStatus();
        $this->testAddNote();
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    private function testSeedAndList(): void
    {
        $store = new MemoryCycleStore();
        $output = new Output(true);
        $app = new App($output);

        $exitCode = $app->handle(['app', 'seed'], $store);
        $this->assertTrue($exitCode === 0, 'Seed should succeed.');

        $exitCode = $app->handle(['app', 'list'], $store);
        $this->assertTrue($exitCode === 0, 'List should succeed.');

        $lines = implode("\n", $output->lines());
        $this->assertTrue(str_contains($lines, 'Spring 2026 Scholarship Cycle'), 'Seeded cycle should appear.');
    }

    private function testAddCycle(): void
    {
        $store = new MemoryCycleStore();
        $output = new Output(true);
        $app = new App($output);

        $exitCode = $app->handle([
            'app',
            'add-cycle',
            'Winter 2027 Scholarship Cycle',
            '2027-01-10',
            '2027-04-15',
            'Operations',
        ], $store);

        $this->assertTrue($exitCode === 0, 'Add-cycle should succeed.');
        $this->assertTrue(count($store->cycles) === 1, 'Cycle should be added.');
    }

    private function testUpdateStatus(): void
    {
        $store = new MemoryCycleStore();
        $store->addCycle('Test Cycle', '2026-01-01', '2026-02-01', 'Owner');
        $output = new Output(true);
        $app = new App($output);

        $exitCode = $app->handle(['app', 'update-status', '1', 'complete'], $store);
        $this->assertTrue($exitCode === 0, 'Update-status should succeed.');
        $this->assertTrue($store->cycles[0]['status'] === 'complete', 'Status should update.');
    }

    private function testAddNote(): void
    {
        $store = new MemoryCycleStore();
        $store->addCycle('Test Cycle', '2026-01-01', '2026-02-01', 'Owner');
        $output = new Output(true);
        $app = new App($output);

        $exitCode = $app->handle(['app', 'add-note', '1', 'Follow up with reviewers'], $store);
        $this->assertTrue($exitCode === 0, 'Add-note should succeed.');
        $this->assertTrue(count($store->notes) === 1, 'Note should be added.');
    }
}
