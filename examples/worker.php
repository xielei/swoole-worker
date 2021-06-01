<?php

declare(strict_types=1);

use Xielei\Swoole\Worker;

require_once __DIR__ . '/vendor/autoload.php';

$worker = new Worker('127.0.0.1', 9327, 'this is secret_key..');

$worker::$debug_mode = true;

$worker->worker_file = __DIR__ . '/Event.php';

$worker->start();
