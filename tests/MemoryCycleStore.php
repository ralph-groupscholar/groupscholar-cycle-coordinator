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

        $springId = $this->addCycle('Spring 2026 Scholarship Cycle', '2026-02-01', '2026-06-30', 'Program Ops');
        $fallId = $this->addCycle('Fall 2026 Scholarship Cycle', '2026-08-01', '2026-12-15', 'Scholar Success');
        $this->updateStatus($springId, 'in-progress');

        $launchId = $this->addMilestone($springId, 'Application window launch', '2026-02-05', 'Community Team');
        $reviewId = $this->addMilestone($springId, 'Review sprint #1', '2026-03-10', 'Review Leads');
        $this->addMilestone($fallId, 'Scholar outreach kickoff', '2026-07-15', 'Engagement');
        $this->updateMilestoneStatus($launchId, 'complete');
        $this->updateMilestoneStatus($reviewId, 'in-progress');
        $this->addNote($springId, 'Ensure reviewer onboarding is complete by Feb 12.');
        $this->addNote($springId, 'Confirm award budget ceiling with finance.');
        $this->addNote($fallId, 'Draft outreach playbook for August cohort.');

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

    public function getCycle(int $cycleId): ?array
    {
        foreach ($this->listCycles() as $cycle) {
            if ($cycle['id'] === $cycleId) {
                return $cycle;
            }
        }

        return null;
    }

    public function listMilestones(int $cycleId): array
    {
        $rows = [];
        foreach ($this->milestones as $milestone) {
            if ($milestone['cycle_id'] === $cycleId) {
                $rows[] = $milestone;
            }
        }

        usort($rows, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));

        return $rows;
    }

    public function listNotes(int $cycleId): array
    {
        $rows = [];
        foreach ($this->notes as $note) {
            if ($note['cycle_id'] === $cycleId) {
                $rows[] = $note;
            }
        }

        usort($rows, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $rows;
    }

    public function listUpcomingMilestones(int $days): array
    {
        $today = new \DateTimeImmutable('today');
        $cutoff = $today->modify("+{$days} days");
        $rows = [];

        foreach ($this->milestones as $milestone) {
            $due = new \DateTimeImmutable($milestone['due_date']);
            if ($due < $today || $due > $cutoff) {
                continue;
            }

            $cycleName = '';
            foreach ($this->cycles as $cycle) {
                if ($cycle['id'] === $milestone['cycle_id']) {
                    $cycleName = $cycle['name'];
                    break;
                }
            }

            $rows[] = [
                'id' => $milestone['id'],
                'name' => $milestone['name'],
                'due_date' => $milestone['due_date'],
                'owner' => $milestone['owner'],
                'status' => $milestone['status'],
                'cycle_name' => $cycleName,
                'cycle_id' => $milestone['cycle_id'],
                'days_to_due' => (int) $today->diff($due)->format('%a'),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));

        return $rows;
    }

    public function listOverdueMilestones(int $daysBack): array
    {
        $today = new \DateTimeImmutable('today');
        $cutoff = $today->modify("-{$daysBack} days");
        $rows = [];

        foreach ($this->milestones as $milestone) {
            $due = new \DateTimeImmutable($milestone['due_date']);
            if ($due >= $today || $due < $cutoff) {
                continue;
            }
            if ($milestone['status'] === 'complete') {
                continue;
            }

            $cycleName = '';
            foreach ($this->cycles as $cycle) {
                if ($cycle['id'] === $milestone['cycle_id']) {
                    $cycleName = $cycle['name'];
                    break;
                }
            }

            $rows[] = [
                'id' => $milestone['id'],
                'name' => $milestone['name'],
                'due_date' => $milestone['due_date'],
                'owner' => $milestone['owner'],
                'status' => $milestone['status'],
                'cycle_name' => $cycleName,
                'cycle_id' => $milestone['cycle_id'],
                'days_overdue' => (int) $due->diff($today)->format('%a'),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));

        return $rows;
    }

    public function listCycleHealth(int $upcomingDays): array
    {
        $today = new \DateTimeImmutable('today');
        $cutoff = $today->modify("+{$upcomingDays} days");
        $rows = [];

        foreach ($this->cycles as $cycle) {
            $milestones = array_filter(
                $this->milestones,
                fn ($milestone) => $milestone['cycle_id'] === $cycle['id']
            );

            $total = count($milestones);
            $complete = 0;
            $inProgress = 0;
            $planned = 0;
            $overdue = 0;
            $upcoming = 0;

            foreach ($milestones as $milestone) {
                if ($milestone['status'] === 'complete') {
                    $complete++;
                } elseif ($milestone['status'] === 'in-progress') {
                    $inProgress++;
                } else {
                    $planned++;
                }

                $due = new \DateTimeImmutable($milestone['due_date']);
                if ($due < $today && $milestone['status'] !== 'complete') {
                    $overdue++;
                }

                if ($due >= $today && $due <= $cutoff) {
                    $upcoming++;
                }
            }

            $rows[] = [
                'id' => $cycle['id'],
                'name' => $cycle['name'],
                'status' => $cycle['status'],
                'owner' => $cycle['owner'],
                'start_date' => $cycle['start_date'],
                'end_date' => $cycle['end_date'],
                'milestone_total' => $total,
                'milestone_complete' => $complete,
                'milestone_in_progress' => $inProgress,
                'milestone_planned' => $planned,
                'milestone_overdue' => $overdue,
                'milestone_upcoming' => $upcoming,
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['start_date'], $b['start_date']));

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

    public function updateMilestoneStatus(int $milestoneId, string $status): int
    {
        $updated = 0;
        foreach ($this->milestones as &$milestone) {
            if ($milestone['id'] === $milestoneId) {
                $milestone['status'] = $status;
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
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $id;
    }
}
