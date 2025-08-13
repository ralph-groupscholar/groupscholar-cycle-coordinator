<?php

namespace GroupScholar\CycleCoordinator;

use PDO;
use RuntimeException;

class CycleRepository implements CycleStore
{
    private PDO $pdo;
    private string $schema = 'groupscholar_cycle_coordinator';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function initialize(): void
    {
        $this->pdo->exec("CREATE SCHEMA IF NOT EXISTS {$this->schema}");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->schema}.cycles (
            id BIGSERIAL PRIMARY KEY,
            name TEXT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            status TEXT NOT NULL DEFAULT 'planned',
            owner TEXT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->schema}.milestones (
            id BIGSERIAL PRIMARY KEY,
            cycle_id BIGINT NOT NULL REFERENCES {$this->schema}.cycles(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            due_date DATE NOT NULL,
            owner TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'planned',
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->schema}.notes (
            id BIGSERIAL PRIMARY KEY,
            cycle_id BIGINT NOT NULL REFERENCES {$this->schema}.cycles(id) ON DELETE CASCADE,
            note TEXT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )");
    }

    public function seed(): int
    {
        $this->initialize();

        $existing = (int) $this->pdo->query("SELECT COUNT(*) FROM {$this->schema}.cycles")->fetchColumn();
        if ($existing > 0) {
            return 0;
        }

        $this->pdo->beginTransaction();

        $insertCycle = $this->pdo->prepare(
            "INSERT INTO {$this->schema}.cycles (name, start_date, end_date, status, owner)
             VALUES (:name, :start_date, :end_date, :status, :owner)
             RETURNING id"
        );

        $cycles = [
            [
                'name' => 'Spring 2026 Scholarship Cycle',
                'start_date' => '2026-02-01',
                'end_date' => '2026-06-30',
                'status' => 'in-progress',
                'owner' => 'Program Ops',
            ],
            [
                'name' => 'Fall 2026 Scholarship Cycle',
                'start_date' => '2026-08-01',
                'end_date' => '2026-12-15',
                'status' => 'planned',
                'owner' => 'Scholar Success',
            ],
        ];

        $cycleIds = [];
        foreach ($cycles as $cycle) {
            $insertCycle->execute($cycle);
            $cycleIds[] = (int) $insertCycle->fetchColumn();
        }

        $insertMilestone = $this->pdo->prepare(
            "INSERT INTO {$this->schema}.milestones (cycle_id, name, due_date, owner, status)
             VALUES (:cycle_id, :name, :due_date, :owner, :status)"
        );

        $milestones = [
            [
                'cycle_id' => $cycleIds[0],
                'name' => 'Application window launch',
                'due_date' => '2026-02-05',
                'owner' => 'Community Team',
                'status' => 'complete',
            ],
            [
                'cycle_id' => $cycleIds[0],
                'name' => 'Review sprint #1',
                'due_date' => '2026-03-10',
                'owner' => 'Review Leads',
                'status' => 'in-progress',
            ],
            [
                'cycle_id' => $cycleIds[1],
                'name' => 'Scholar outreach kickoff',
                'due_date' => '2026-07-15',
                'owner' => 'Engagement',
                'status' => 'planned',
            ],
        ];

        foreach ($milestones as $milestone) {
            $insertMilestone->execute($milestone);
        }

        $insertNote = $this->pdo->prepare(
            "INSERT INTO {$this->schema}.notes (cycle_id, note) VALUES (:cycle_id, :note)"
        );

        $notes = [
            [
                'cycle_id' => $cycleIds[0],
                'note' => 'Ensure reviewer onboarding is complete by Feb 12.',
            ],
            [
                'cycle_id' => $cycleIds[0],
                'note' => 'Confirm award budget ceiling with finance.',
            ],
            [
                'cycle_id' => $cycleIds[1],
                'note' => 'Draft outreach playbook for August cohort.',
            ],
        ];

        foreach ($notes as $note) {
            $insertNote->execute($note);
        }

        $this->pdo->commit();

        return count($cycles);
    }

    public function listCycles(): array
    {
        $sql = "SELECT c.id, c.name, c.status, c.owner,
                       c.start_date, c.end_date,
                       COUNT(DISTINCT m.id) AS milestone_count,
                       COUNT(DISTINCT n.id) AS note_count
                FROM {$this->schema}.cycles c
                LEFT JOIN {$this->schema}.milestones m ON m.cycle_id = c.id
                LEFT JOIN {$this->schema}.notes n ON n.cycle_id = c.id
                GROUP BY c.id
                ORDER BY c.start_date";

        return $this->pdo->query($sql)->fetchAll();
    }

    public function getCycle(int $cycleId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.name, c.status, c.owner,
                    c.start_date, c.end_date,
                    COUNT(DISTINCT m.id) AS milestone_count,
                    COUNT(DISTINCT n.id) AS note_count
             FROM {$this->schema}.cycles c
             LEFT JOIN {$this->schema}.milestones m ON m.cycle_id = c.id
             LEFT JOIN {$this->schema}.notes n ON n.cycle_id = c.id
             WHERE c.id = :id
             GROUP BY c.id"
        );

        $stmt->execute(['id' => $cycleId]);
        $cycle = $stmt->fetch();

        return $cycle === false ? null : $cycle;
    }

    public function listMilestones(int $cycleId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, due_date, owner, status
             FROM {$this->schema}.milestones
             WHERE cycle_id = :cycle_id
             ORDER BY due_date"
        );

        $stmt->execute(['cycle_id' => $cycleId]);

        return $stmt->fetchAll();
    }

    public function listNotes(int $cycleId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, note, created_at
             FROM {$this->schema}.notes
             WHERE cycle_id = :cycle_id
             ORDER BY created_at DESC"
        );

        $stmt->execute(['cycle_id' => $cycleId]);

        return $stmt->fetchAll();
    }

    public function listUpcomingMilestones(int $days): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.id, m.name, m.due_date, m.owner, m.status,
                    c.id AS cycle_id, c.name AS cycle_name,
                    (m.due_date - CURRENT_DATE) AS days_to_due
             FROM {$this->schema}.milestones m
             JOIN {$this->schema}.cycles c ON c.id = m.cycle_id
             WHERE m.due_date >= CURRENT_DATE
               AND m.due_date <= CURRENT_DATE + (:days * INTERVAL '1 day')
             ORDER BY m.due_date"
        );

        $stmt->execute(['days' => $days]);

        return $stmt->fetchAll();
    }

    public function addCycle(string $name, string $startDate, string $endDate, string $owner): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->schema}.cycles (name, start_date, end_date, owner)
             VALUES (:name, :start_date, :end_date, :owner)
             RETURNING id"
        );

        $stmt->execute([
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'owner' => $owner,
        ]);

        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Failed to create cycle.');
        }

        return (int) $id;
    }

    public function addMilestone(int $cycleId, string $name, string $dueDate, string $owner): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->schema}.milestones (cycle_id, name, due_date, owner)
             VALUES (:cycle_id, :name, :due_date, :owner)
             RETURNING id"
        );

        $stmt->execute([
            'cycle_id' => $cycleId,
            'name' => $name,
            'due_date' => $dueDate,
            'owner' => $owner,
        ]);

        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Failed to create milestone.');
        }

        return (int) $id;
    }

    public function updateStatus(int $cycleId, string $status): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->schema}.cycles SET status = :status WHERE id = :id"
        );

        $stmt->execute([
            'status' => $status,
            'id' => $cycleId,
        ]);

        return $stmt->rowCount();
    }

    public function updateMilestoneStatus(int $milestoneId, string $status): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->schema}.milestones SET status = :status WHERE id = :id"
        );

        $stmt->execute([
            'status' => $status,
            'id' => $milestoneId,
        ]);

        return $stmt->rowCount();
    }

    public function addNote(int $cycleId, string $note): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->schema}.notes (cycle_id, note)
             VALUES (:cycle_id, :note)
             RETURNING id"
        );

        $stmt->execute([
            'cycle_id' => $cycleId,
            'note' => $note,
        ]);

        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Failed to create note.');
        }

        return (int) $id;
    }
}
