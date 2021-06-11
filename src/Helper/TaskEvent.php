<?php

declare(strict_types=1);

namespace Xielei\Swoole\Helper;

use Xielei\Swoole\Interfaces\TaskEventInterface;
use Xielei\Swoole\Worker;

class TaskEvent implements TaskEventInterface
{
    public $worker;

    public function __construct(Worker $worker)
    {
        $this->worker = $worker;
    }

    public function onWorkerStart()
    {
    }

    public function onWorkerExit()
    {
    }

    public function onWorkerStop()
    {
    }

    public function onTask(int $task_id, int $src_worker_id, $data)
    {
    }

    public function onPipeMessage(int $src_worker_id, $message)
    {
    }
}
