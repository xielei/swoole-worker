<?php

declare (strict_types = 1);

use Swoole\Timer;
use Xielei\Swoole\Api;
use Xielei\Swoole\Helper\TaskEvent as HelperTaskEvent;
use Xielei\Swoole\Protocol;

require_once __DIR__ . '/vendor/autoload.php';

class TaskEvent extends HelperTaskEvent
{
    public function onWorkerStart()
    {}

    public function onWorkerExit()
    {}

    public function onWorkerStop()
    {}

    public function onTask(int $task_id, int $src_worker_id, $data)
    {}

    public function onPipeMessage(int $src_worker_id, $message)
    {}
}