<?php

declare(strict_types=1);

use Xielei\Swoole\Api;
use Xielei\Swoole\Helper\WorkerEvent as HelperWorkerEvent;

class WorkerEvent extends HelperWorkerEvent
{
    public function onConnect(string $client)
    {
        Api::sendToAll("{$client} connect");
    }

    public function onReceive(string $client, string $data)
    {
        Api::sendToAll("{$client} say {$data}");
    }

    public function onClose(string $client, array $bind)
    {
        Api::sendToAll("{$client} exit~");
    }
}
