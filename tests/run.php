<?php

require __DIR__ . '/AppTest.php';

use GroupScholar\CycleCoordinator\Tests\AppTest;

$test = new AppTest();
$test->run();

fwrite(STDOUT, "All tests passed.\n");
