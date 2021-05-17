<?php

declare(strict_types=1);

namespace Xielei\Swoole;

class Event
{
    /**
     * worker start
     *
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStart(Worker $worker)
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
     * @param array $bind
     * @return void
     */
    public function onClose(string $client, array $bind)
    {
    }

    /**
     * worker stop
     *
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStop(Worker $worker)
    {
    }
}
