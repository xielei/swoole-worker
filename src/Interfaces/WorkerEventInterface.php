<?php

declare(strict_types=1);

namespace Xielei\Swoole\Interfaces;

use Swoole\Server\PipeMessage;
use Swoole\Server\TaskResult;

interface WorkerEventInterface
{
    /**
     * worker start
     *
     * @return void
     */
    public function onWorkerStart();

    /**
     * worker stop
     *
     * @return void
     */
    public function onWorkerStop();

    /**
     * worker exit
     *
     * @return void
     */
    public function onWorkerExit();

    /**
     * pipe message
     *
     * @param PipeMessage $pipeMessage
     * @return void
     */
    public function onPipeMessage(PipeMessage $pipeMessage);

    /**
     * task finish
     *
     * @param TaskResult $taskResult
     * @return void
     */
    public function onFinish(TaskResult $taskResult);

    /**
     * tcp connect
     *
     * @param string $client
     * @return void
     */
    public function onConnect(string $client);

    /**
     * tcp receive
     *
     * @param string $client
     * @param string $data
     * @return void
     */
    public function onReceive(string $client, string $data);

    /**
     * websocket open
     *
     * @param string $client
     * @param array $request
     * @return void
     */
    public function onOpen(string $client, array $request);

    /**
     * websocket message
     *
     * @param string $client
     * @param array $frame
     * @return void
     */
    public function onMessage(string $client, array $frame);

    /**
     * tcp close
     *
     * @param string $client
     * @param array $bind
     * @return void
     */
    public function onClose(string $client, array $bind);
}