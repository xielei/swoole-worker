<?php

use Swoole\Server;
use Xielei\Swoole\Protocol;
use Xielei\Swoole\Worker;

/**
 * @var Worker $this
 */

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
                'listens' => json_encode($this->listens),
                'worker_pool_list' => json_encode(array_keys($this->worker_pool_list)),
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

$this->on('PortConnect', function (Server $server, int $fd) {
    $this->sendToProcess([
        'event' => Protocol::CLIENT_CONNECT,
        'fd' => $fd,
    ]);
});

$this->on('PortReceive', function (Server $server, int $fd, int $reactor_id, string $message) {
    $this->sendToProcess([
        'event' => Protocol::CLIENT_MESSAGE,
        'fd' => $fd,
        'message' => $message,
    ]);
});

$this->on('PortOpen', function (Server $server, $request) {
    $this->sendToProcess([
        'event' => Protocol::CLIENT_WEBSOCKET_CONNECT,
        'fd' => $request->fd,
        'extra' => [
            'header' => $request->header,
            'server' => $request->server,
            'get' => $request->get,
            'post' => $request->post,
            'cookie' => $request->cookie,
            'files' => $request->files,
        ],
    ]);
});

$this->on('PortMessage', function (Server $server, $frame) {
    switch ($frame->opcode) {
        case WEBSOCKET_OPCODE_TEXT:
        case WEBSOCKET_OPCODE_BINARY:
            $this->sendToProcess([
                'event' => Protocol::CLIENT_MESSAGE,
                'fd' => $frame->fd,
                'message' => $frame->data,
            ]);
            break;

        default:
            break;
    }
});

$this->on('PortClose', function (Server $server, int $fd) {
    $this->sendToProcess([
        'event' => Protocol::CLIENT_CLOSE,
        'fd' => $fd,
    ]);
});
