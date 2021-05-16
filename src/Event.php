<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Swoole\Process\Pool;

class Event
{
    /**
     * worker start
     *
     * @param Pool $pool
     * @param integer $worker_id
     * @return void
     */
    public function onWorkerStart(Pool $pool, int $worker_id)
    {
    }

    /**
     * client connect
     *
     * @param string $client
     * @return void
     */
    public function onConnect(string $client)
    {
    }

    /**
     * websocket connect
     *
     * @param string $client
     * @param array $global
     * @return void
     */
    public function onWebsocketConnect(string $client, array $global)
    {
    }

    /**
     * client message
     *
     * @param string $client
     * @param string $data
     * @return void
     */
    public function onMessage(string $client, string $data)
    {
    }

    /**
     * client close
     *
     * @param string $client
     * @return void
     */
    public function onClose(string $client, array $bind)
    {
    }

    /**
     * worker stop
     *
     * @param Pool $pool
     * @param integer $worker_id
     * @return void
     */
    public function onWorkerStop(Pool $pool, int $worker_id)
    {
    }
}
