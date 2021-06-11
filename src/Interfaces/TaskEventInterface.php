<?php

declare(strict_types=1);

namespace Xielei\Swoole\Interfaces;

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
     * @param integer $src_worker_id
     * @param [mixed] $message
     * @return void
     */
    public function onPipeMessage(int $src_worker_id, $message);

    /**
     * task
     *
     * @param integer $task_id
     * @param integer $src_worker_id
     * @param [mixed] $data
     * @return void
     */
    public function onTask(int $task_id, int $src_worker_id, $data);
}
