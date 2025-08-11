#!/usr/bin/env php
<?php

require __DIR__ . '/../src/App.php';
require __DIR__ . '/../src/CycleStore.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/CycleRepository.php';
require __DIR__ . '/../src/Output.php';

use GroupScholar\CycleCoordinator\App;
use GroupScholar\CycleCoordinator\Database;
use GroupScholar\CycleCoordinator\CycleRepository;
use GroupScholar\CycleCoordinator\Output;

$argv = $_SERVER['argv'];

$output = new Output();
$app = new App($output);

try {
    $database = Database::fromEnvironment();
    $repository = new CycleRepository($database->pdo());
    $exitCode = $app->handle($argv, $repository);
} catch (Throwable $exception) {
    $output->error($exception->getMessage());
    $exitCode = 1;
}

exit($exitCode);
