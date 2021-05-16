<?php

declare(strict_types=1);

use Xielei\Swoole\Register;

require_once __DIR__ . '/../vendor/autoload.php';

$register = new Register('127.0.0.1', 3327);

$register->secret_key = 'this is secret_key..';

$register->start();
