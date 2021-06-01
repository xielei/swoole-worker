<?php

declare (strict_types = 1);

namespace Xielei\Swoole\Interfaces;

interface TaskEventInterface
{
    /**
     * on task worker start
     *
     * @return void
     */
    public function onWorkerStart();

    /**
     * on task worker stop
     *
     * @return void
     */
    public function onWorkerStop();

    /**
     * on task worker exit
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
     * on task
     *
     * @param integer $task_id
     * @param integer $src_worker_id
     * @param [mixed] $data
     * @return void
     */
    public function onTask(int $task_id, int $src_worker_id, $data);
}
