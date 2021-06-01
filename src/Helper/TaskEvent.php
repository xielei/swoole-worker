<?php

declare (strict_types = 1);

namespace Xielei\Swoole\Helper;

use Swoole\Server;
use Xielei\Swoole\Interfaces\TaskEventInterface;

class TaskEvent implements TaskEventInterface
{
    public $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

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
