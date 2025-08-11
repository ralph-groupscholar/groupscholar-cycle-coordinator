<?php

namespace GroupScholar\CycleCoordinator;

interface CycleStore
{
    public function initialize(): void;
    public function seed(): int;
    public function listCycles(): array;
    public function addCycle(string $name, string $startDate, string $endDate, string $owner): int;
    public function addMilestone(int $cycleId, string $name, string $dueDate, string $owner): int;
    public function updateStatus(int $cycleId, string $status): int;
    public function addNote(int $cycleId, string $note): int;
}
