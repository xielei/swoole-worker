<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Swoole\Process\Pool;

class Event
{
    /**
     * 工作进程启动
     *
     * @param Pool $pool
     * @param integer $worker_id
     * @return void
     */
    public function onWorkerStart(Pool $pool, int $worker_id)
    {
    }

    /**
     * 客户端连接
     *
     * @param string $client
     * @return void
     */
    public function onConnect(string $client)
    {
    }

    /**
     * websocket客户端链接
     *
     * @param string $client
     * @param array $global
     * @return void
     */
    public function onWebsocketConnect(string $client, array $global)
    {
    }

    /**
     * 收到客户端消息
     *
     * @param string $client
     * @param string $data
     * @return void
     */
    public function onMessage(string $client, string $data)
    {
    }

    /**
     * 客户端关闭
     *
     * @param string $client
     * @return void
     */
    public function onClose(string $client, array $bind)
    {
    }

    /**
     * 工作进程停止
     *
     * @param Pool $pool
     * @param integer $worker_id
     * @return void
     */
    public function onWorkerStop(Pool $pool, int $worker_id)
    {
    }
}
