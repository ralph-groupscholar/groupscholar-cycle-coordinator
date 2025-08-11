<?php

namespace GroupScholar\CycleCoordinator\Tests;

use GroupScholar\CycleCoordinator\CycleStore;

class MemoryCycleStore implements CycleStore
{
    private int $nextCycleId = 1;
    private int $nextMilestoneId = 1;
    private int $nextNoteId = 1;

    public array $cycles = [];
    public array $milestones = [];
    public array $notes = [];

    public function initialize(): void
    {
        // No-op for memory store.
    }

    public function seed(): int
    {
        $this->cycles = [];
        $this->milestones = [];
        $this->notes = [];
        $this->nextCycleId = 1;
        $this->nextMilestoneId = 1;
        $this->nextNoteId = 1;

        $this->addCycle('Spring 2026 Scholarship Cycle', '2026-02-01', '2026-06-30', 'Program Ops');
        $this->addCycle('Fall 2026 Scholarship Cycle', '2026-08-01', '2026-12-15', 'Scholar Success');
        $this->addMilestone(1, 'Application window launch', '2026-02-05', 'Community Team');
        $this->addMilestone(1, 'Review sprint #1', '2026-03-10', 'Review Leads');
        $this->addMilestone(2, 'Scholar outreach kickoff', '2026-07-15', 'Engagement');
        $this->addNote(1, 'Ensure reviewer onboarding is complete by Feb 12.');
        $this->addNote(1, 'Confirm award budget ceiling with finance.');
        $this->addNote(2, 'Draft outreach playbook for August cohort.');

        return 2;
    }

    public function listCycles(): array
    {
        $rows = [];
        foreach ($this->cycles as $cycle) {
            $milestoneCount = 0;
            $noteCount = 0;
            foreach ($this->milestones as $milestone) {
                if ($milestone['cycle_id'] === $cycle['id']) {
                    $milestoneCount++;
                }
            }
            foreach ($this->notes as $note) {
                if ($note['cycle_id'] === $cycle['id']) {
                    $noteCount++;
                }
            }
            $rows[] = [
                'id' => $cycle['id'],
                'name' => $cycle['name'],
                'status' => $cycle['status'],
                'owner' => $cycle['owner'],
                'start_date' => $cycle['start_date'],
                'end_date' => $cycle['end_date'],
                'milestone_count' => $milestoneCount,
                'note_count' => $noteCount,
            ];
        }

        return $rows;
    }

    public function addCycle(string $name, string $startDate, string $endDate, string $owner): int
    {
        $id = $this->nextCycleId++;
        $this->cycles[] = [
            'id' => $id,
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'planned',
            'owner' => $owner,
        ];

        return $id;
    }

    public function addMilestone(int $cycleId, string $name, string $dueDate, string $owner): int
    {
        $id = $this->nextMilestoneId++;
        $this->milestones[] = [
            'id' => $id,
            'cycle_id' => $cycleId,
            'name' => $name,
            'due_date' => $dueDate,
            'owner' => $owner,
            'status' => 'planned',
        ];

        return $id;
    }

    public function updateStatus(int $cycleId, string $status): int
    {
        $updated = 0;
        foreach ($this->cycles as &$cycle) {
            if ($cycle['id'] === $cycleId) {
                $cycle['status'] = $status;
                $updated++;
            }
        }

        return $updated;
    }

    public function addNote(int $cycleId, string $note): int
    {
        $id = $this->nextNoteId++;
        $this->notes[] = [
            'id' => $id,
            'cycle_id' => $cycleId,
            'note' => $note,
        ];

        return $id;
    }
}
