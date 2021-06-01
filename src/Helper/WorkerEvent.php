<?php

declare (strict_types = 1);

namespace Xielei\Swoole\Helper;

use Swoole\Server;
use Xielei\Swoole\Interfaces\WorkerEventInterface;

class WorkerEvent implements WorkerEventInterface
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

    public function onFinish(int $task_id, $data)
    {}

    public function onPipeMessage(int $src_worker_id, $message)
    {}

    public function onConnect(string $client)
    {}

    public function onWebsocketConnect(string $client, array $global)
    {}

    public function onMessage(string $client, string $data)
    {}

    public function onClose(string $client, array $bind)
    {}
}
