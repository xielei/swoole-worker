<?php

declare(strict_types=1);

use Xielei\Swoole\Worker;

require_once __DIR__ . '/vendor/autoload.php';

$worker = new Worker();

$worker->register_host = '127.0.0.1';
$worker->register_port = 9327;
$worker->register_secret_key = 'this is secret_key..';

$worker->worker_file = __DIR__ . '/event_worker.php';
$worker->task_file = __DIR__ . '/event_task.php';

$worker->set([
    'worker_num' => 2,
    'task_worker_num' => 2,
    'log_file ' => __DIR__ . '/worker.log',
    'stats_file ' => __DIR__ . '/worker.stats.log',
]);

$worker->$worker->start();
