<?php

declare(strict_types=1);

namespace Xielei\Swoole\Interfaces;

use Swoole\Server\PipeMessage;
use Swoole\Server\Task;

interface TaskEventInterface
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
     * task
     *
     * @param Task $task
     * @return void
     */
    public function onTask(Task $task);
}
