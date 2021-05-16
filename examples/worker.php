<?php

declare(strict_types=1);

use Xielei\Swoole\Worker;

require_once __DIR__ . '/../vendor/autoload.php';

include __DIR__ . '/Event.php';

$worker = new Worker(new Event, 3);

$worker->register_secret_key = 'this is secret_key..';

$worker->start();
