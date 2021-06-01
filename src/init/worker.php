<?php

use function Composer\Autoload\includeFile;
use Swoole\Server;
use Xielei\Swoole\Api;
use Xielei\Swoole\Protocol;
use Xielei\Swoole\Worker;

/**
 * @var Worker $this
 */

$this->on('WorkerStart', function (Server $server, int $worker_id, ...$args) {
    $this->sendToProcess([
        'event' => 'gateway_address_list',
        'worker_id' => $worker_id,
    ]);
    Api::$address_list = &$this->gateway_address_list;
    if ($server->taskworker) {
        includeFile($this->task_file);
        $this->event = new \TaskEvent($server);
    } else {
        includeFile($this->worker_file);
        $this->event = new \WorkerEvent($server);
    }
    call_user_func([$this->event, 'onWorkerStart'], $server, $worker_id, ...$args);
});

$this->on('WorkerExit', function (...$args) {
    call_user_func([$this->event, 'onWorkerExit'], ...$args);
});

$this->on('WorkerStop', function (...$args) {
    call_user_func([$this->event, 'onWorkerStop'], ...$args);
});
$this->on('Task', function (...$args) {
    call_user_func([$this->event, 'onTask'], ...$args);
});
$this->on('PipeMessage', function (Server $server, int $src_worker_id, $message) {
    if ($src_worker_id >= $server->setting['worker_num'] + $server->setting['task_worker_num']) {
        $data = unserialize($message);
        switch ($data['event']) {
            case 'gateway_address_list':
                $this->gateway_address_list = $data['gateway_address_list'];
                break;
            case 'gateway_event':
                $this->onGatewayMessage($data['buffer'], $data['address']);
                break;
            default:
                break;
        }
    } else {
        call_user_func([$this->event, 'onPipeMessage'], $server, $src_worker_id, $message);
    }
});
$this->on('Finish', function (...$args) {
    call_user_func([$this->event, 'onFinish'], ...$args);
});

$this->on('Receive', function (Server $server, int $fd, int $reactorId, string $buffer) {
    $data = unpack('Ccmd/A*load', Protocol::decode($buffer));
    switch ($data['cmd']) {
        case Protocol::PING:
            break;

        case Protocol::SERVER_RELOAD:
            $server->reload();
            break;

        case Protocol::SERVER_STATUS:
            $status = $server->stats() + [
                'register_host' => $this->register_host,
                'register_port' => $this->register_port,
                'register_secret_key' => $this->register_secret_key,
                'daemonize' => $this->daemonize,
                'log_file' => $this->log_file,
                'pid_file' => $this->pid_file,
                'inner_socket' => $this->inner_socket,
                'gateway_address_list' => json_encode($this->gateway_address_list),
            ];
            $status['start_time'] = date(DATE_ISO8601, $status['start_time']);
            $load = json_encode($status);
            $server->send($fd, Protocol::encode($load));
            break;

        default:
            $server->close($fd);
            break;
    }
});
