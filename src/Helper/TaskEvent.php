<?php

declare(strict_types=1);

namespace Xielei\Swoole\Helper;

use Swoole\Server\PipeMessage;
use Swoole\Server\Task;
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

    public function onTask(Task $task)
    {
    }

    public function onPipeMessage(PipeMessage $pipeMessage)
    {
    }
}
