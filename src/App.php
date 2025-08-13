<?php

namespace GroupScholar\CycleCoordinator;

class App
{
    private Output $output;

    public function __construct(Output $output)
    {
        $this->output = $output;
    }

    public function handle(array $argv, CycleStore $repository): int
    {
        $command = $argv[1] ?? 'help';

        switch ($command) {
            case 'help':
                $this->printHelp();
                return 0;
            case 'init':
                $repository->initialize();
                $this->output->success('Database schema ready.');
                return 0;
            case 'seed':
                $count = $repository->seed();
                $this->output->success("Seeded {$count} cycles.");
                return 0;
            case 'list':
                $cycles = $repository->listCycles();
                $this->renderCycles($cycles);
                return 0;
            case 'add-cycle':
                return $this->handleAddCycle($argv, $repository);
            case 'add-milestone':
                return $this->handleAddMilestone($argv, $repository);
            case 'update-status':
                return $this->handleUpdateStatus($argv, $repository);
            case 'update-milestone':
                return $this->handleUpdateMilestone($argv, $repository);
            case 'add-note':
                return $this->handleAddNote($argv, $repository);
            case 'cycle':
                return $this->handleShowCycle($argv, $repository);
            case 'upcoming':
                return $this->handleUpcoming($argv, $repository);
            default:
                $this->output->error("Unknown command: {$command}");
                $this->printHelp();
                return 1;
        }
    }

    private function handleAddCycle(array $argv, CycleStore $repository): int
    {
        $name = $argv[2] ?? null;
        $startDate = $argv[3] ?? null;
        $endDate = $argv[4] ?? null;
        $owner = $argv[5] ?? null;

        if (!$name || !$startDate || !$endDate || !$owner) {
            $this->output->error('Usage: add-cycle "Name" 2026-02-01 2026-06-01 "Owner"');
            return 1;
        }

        $cycleId = $repository->addCycle($name, $startDate, $endDate, $owner);
        $this->output->success("Cycle created with ID {$cycleId}.");
        return 0;
    }

    private function handleAddMilestone(array $argv, CycleStore $repository): int
    {
        $cycleId = $argv[2] ?? null;
        $name = $argv[3] ?? null;
        $dueDate = $argv[4] ?? null;
        $owner = $argv[5] ?? null;

        if (!$cycleId || !$name || !$dueDate || !$owner) {
            $this->output->error('Usage: add-milestone 1 "Kickoff" 2026-02-15 "Owner"');
            return 1;
        }

        $milestoneId = $repository->addMilestone((int) $cycleId, $name, $dueDate, $owner);
        $this->output->success("Milestone created with ID {$milestoneId}.");
        return 0;
    }

    private function handleUpdateStatus(array $argv, CycleStore $repository): int
    {
        $cycleId = $argv[2] ?? null;
        $status = $argv[3] ?? null;

        if (!$cycleId || !$status) {
            $this->output->error('Usage: update-status 1 "in-progress"');
            return 1;
        }

        $updated = $repository->updateStatus((int) $cycleId, $status);
        if ($updated === 0) {
            $this->output->error('No cycle updated. Check the ID.');
            return 1;
        }

        $this->output->success('Status updated.');
        return 0;
    }

    private function handleUpdateMilestone(array $argv, CycleStore $repository): int
    {
        $milestoneId = $argv[2] ?? null;
        $status = $argv[3] ?? null;

        if (!$milestoneId || !$status) {
            $this->output->error('Usage: update-milestone 1 "complete"');
            return 1;
        }

        $updated = $repository->updateMilestoneStatus((int) $milestoneId, $status);
        if ($updated === 0) {
            $this->output->error('No milestone updated. Check the ID.');
            return 1;
        }

        $this->output->success('Milestone status updated.');
        return 0;
    }

    private function handleAddNote(array $argv, CycleStore $repository): int
    {
        $cycleId = $argv[2] ?? null;
        $note = $argv[3] ?? null;

        if (!$cycleId || !$note) {
            $this->output->error('Usage: add-note 1 "Note text"');
            return 1;
        }

        $noteId = $repository->addNote((int) $cycleId, $note);
        $this->output->success("Note created with ID {$noteId}.");
        return 0;
    }

    private function handleShowCycle(array $argv, CycleStore $repository): int
    {
        $cycleId = $argv[2] ?? null;

        if (!$cycleId) {
            $this->output->error('Usage: cycle 1');
            return 1;
        }

        $cycle = $repository->getCycle((int) $cycleId);
        if ($cycle === null) {
            $this->output->error('Cycle not found.');
            return 1;
        }

        $this->renderCycleDetail($cycle, $repository->listMilestones((int) $cycleId), $repository->listNotes((int) $cycleId));
        return 0;
    }

    private function handleUpcoming(array $argv, CycleStore $repository): int
    {
        $days = $argv[2] ?? '30';
        if (!is_numeric($days) || (int) $days <= 0) {
            $this->output->error('Usage: upcoming 30');
            return 1;
        }

        $milestones = $repository->listUpcomingMilestones((int) $days);
        if (count($milestones) === 0) {
            $this->output->info('No upcoming milestones found.');
            return 0;
        }

        $rows = [];
        foreach ($milestones as $milestone) {
            $rows[] = [
                $milestone['id'],
                $milestone['name'],
                $milestone['due_date'],
                $milestone['owner'],
                $milestone['status'],
                $milestone['cycle_name'],
                $milestone['days_to_due'],
            ];
        }

        $this->output->table(
            ['ID', 'Milestone', 'Due', 'Owner', 'Status', 'Cycle', 'Days'],
            $rows
        );

        return 0;
    }

    private function renderCycles(array $cycles): void
    {
        if (count($cycles) === 0) {
            $this->output->info('No cycles found.');
            return;
        }

        $rows = [];
        foreach ($cycles as $cycle) {
            $rows[] = [
                $cycle['id'],
                $cycle['name'],
                $cycle['status'],
                $cycle['owner'],
                $cycle['start_date'],
                $cycle['end_date'],
                $cycle['milestone_count'],
                $cycle['note_count'],
            ];
        }

        $this->output->table(
            ['ID', 'Name', 'Status', 'Owner', 'Start', 'End', 'Milestones', 'Notes'],
            $rows
        );
    }

    private function renderCycleDetail(array $cycle, array $milestones, array $notes): void
    {
        $this->output->line("Cycle {$cycle['id']}: {$cycle['name']}");
        $this->output->line("Status: {$cycle['status']} | Owner: {$cycle['owner']}");
        $this->output->line("Dates: {$cycle['start_date']} to {$cycle['end_date']}");
        $this->output->line("Milestones: {$cycle['milestone_count']} | Notes: {$cycle['note_count']}");
        $this->output->line('');

        if (count($milestones) === 0) {
            $this->output->info('No milestones found.');
        } else {
            $rows = [];
            foreach ($milestones as $milestone) {
                $rows[] = [
                    $milestone['id'],
                    $milestone['name'],
                    $milestone['due_date'],
                    $milestone['owner'],
                    $milestone['status'],
                ];
            }
            $this->output->table(['ID', 'Milestone', 'Due', 'Owner', 'Status'], $rows);
        }

        $this->output->line('');

        if (count($notes) === 0) {
            $this->output->info('No notes found.');
        } else {
            $rows = [];
            foreach ($notes as $note) {
                $rows[] = [
                    $note['id'],
                    $note['note'],
                    $note['created_at'],
                ];
            }
            $this->output->table(['ID', 'Note', 'Created'], $rows);
        }
    }

    private function printHelp(): void
    {
        $this->output->line('Group Scholar Cycle Coordinator');
        $this->output->line('Usage:');
        $this->output->line('  help');
        $this->output->line('  init');
        $this->output->line('  seed');
        $this->output->line('  list');
        $this->output->line('  add-cycle "Name" 2026-02-01 2026-06-01 "Owner"');
        $this->output->line('  add-milestone 1 "Kickoff" 2026-02-15 "Owner"');
        $this->output->line('  update-status 1 "in-progress"');
        $this->output->line('  update-milestone 1 "complete"');
        $this->output->line('  add-note 1 "Note text"');
        $this->output->line('  cycle 1');
        $this->output->line('  upcoming 30');
    }
}
