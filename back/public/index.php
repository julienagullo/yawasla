<?php

declare(strict_types=1);

define('APP_VERSION', '0.0.1');

$app = require __DIR__ . '/../src/bootstrap.php';

$app->run();
