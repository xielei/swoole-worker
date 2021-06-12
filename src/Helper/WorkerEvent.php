<?php

declare(strict_types=1);

namespace Xielei\Swoole\Helper;

use Swoole\Server\PipeMessage;
use Swoole\Server\TaskResult;
use Xielei\Swoole\Interfaces\WorkerEventInterface;
use Xielei\Swoole\Worker;

class WorkerEvent implements WorkerEventInterface
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

    public function onFinish(TaskResult $taskResult)
    {
    }

    public function onPipeMessage(PipeMessage $pipeMessage)
    {
    }

    public function onConnect(string $client)
    {
    }

    public function onReceive(string $client, string $data)
    {
    }

    public function onOpen(string $client, array $request)
    {
        $this->onConnect($client);
    }

    public function onMessage(string $client, array $frame)
    {
        switch ($frame['opcode']) {
            case WEBSOCKET_OPCODE_TEXT:
            case WEBSOCKET_OPCODE_BINARY:
                $this->onReceive($client, $frame['data']);
                break;

            default:
                break;
        }
    }

    public function onClose(string $client, array $bind)
    {
    }
}
