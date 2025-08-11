# Group Scholar Cycle Coordinator

CLI to orchestrate scholarship cycle planning: track cycles, milestones, and ops notes with a PostgreSQL-backed logbook.

## Features
- Create and list scholarship cycles with owners and statuses
- Add milestones and notes for each cycle
- Seed realistic sample cycles for a live demo
- PostgreSQL schema isolation via `groupscholar_cycle_coordinator`

## Tech
- PHP 8.5
- PostgreSQL (production)

## Setup
1. Provide a PostgreSQL connection string:

```
export DATABASE_URL="postgres://USER:PASSWORD@HOST:PORT/DBNAME"
```

2. Initialize schema and seed data:

```
php bin/cycle-coordinator.php init
php bin/cycle-coordinator.php seed
```

## Usage
```
php bin/cycle-coordinator.php list
php bin/cycle-coordinator.php add-cycle "Spring 2027 Scholarship Cycle" 2027-02-01 2027-06-30 "Program Ops"
php bin/cycle-coordinator.php add-milestone 1 "Review sprint" 2027-03-10 "Review Leads"
php bin/cycle-coordinator.php update-status 1 "in-progress"
php bin/cycle-coordinator.php add-note 1 "Confirm reviewer onboarding dates"
```

## Tests
```
php tests/run.php
```

## Notes
- Use a production database URL for real data. Do not commit credentials.
- The CLI assumes the schema exists; run `init` once per environment.
