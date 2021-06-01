<?php

declare (strict_types = 1);

namespace Xielei\Swoole\Interfaces;

interface WorkerEventInterface
{
    /**
     * on worker start
     *
     * @return void
     */
    public function onWorkerStart();

    /**
     * on worker stop
     *
     * @return void
     */
    public function onWorkerStop();

    /**
     * on worker exit
     *
     * @return void
     */
    public function onWorkerExit();

    /**
     * on pipe message
     *
     * @param integer $src_worker_id
     * @param [mixed] $message
     * @return void
     */
    public function onPipeMessage(int $src_worker_id, $message);

    /**
     * on finish
     *
     * @param integer $task_id
     * @param [mixed] $data
     * @return void
     */
    public function onFinish(int $task_id, $data);

    /**
     * client connect
     *
     * @param string $client
     * @return void
     */
    public function onConnect(string $client);

    /**
     * on websocket connect
     *
     * @param string $client
     * @param array $global
     * @return void
     */
    public function onWebsocketConnect(string $client, array $global);

    /**
     * on client message
     *
     * @param string $client
     * @param string $data
     * @return void
     */
    public function onMessage(string $client, string $data);

    /**
     * on client close
     *
     * @param string $client
     * @param array $bind
     * @return void
     */
    public function onClose(string $client, array $bind);
}
