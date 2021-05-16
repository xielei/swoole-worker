<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Swoole\Coroutine;
use Swoole\Coroutine\Server as CoServer;
use Swoole\Coroutine\Server\Connection;

class Server
{
    public $onStart;
    public $onConnect;
    public $onMessage;
    public $onClose;
    public $onError;
    public $onStop;

    private $server = null;

    public function __construct(string $host, int $port)
    {
        $server = new CoServer($host, $port);
        $server->set([
            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 0,

            'open_tcp_keepalive' => true,
            'tcp_keepidle' => 1,
            'tcp_keepinterval' => 1,
            'tcp_keepcount' => 5,

            'heartbeat_idle_time' => 5,
            'heartbeat_check_interval' => 1,
        ]);

        $server->handle(function (Connection $conn) {
            $this->emit('connect', $conn);
            while (true) {
                $buffer = $conn->recv();
                if ($buffer === '') {
                    $this->emit('close', $conn);
                    break;
                } elseif ($buffer === false) {
                    $this->emit('error', $conn);
                    if ($conn->errCode !== SOCKET_ETIMEDOUT) {
                        $conn->close(true);
                        $this->emit('close', $conn);
                        break;
                    }
                } elseif ($buffer) {
                    $this->emit('message', $conn, $buffer);
                } else {
                    $conn->close(true);
                    $this->emit('close', $conn);
                    break;
                }
                Coroutine::sleep(0.01);
            }
        });
        $this->server = $server;
    }

    public function start()
    {
        $this->emit('start');
        Coroutine::create(function () {
            $this->server->start();
        });
    }

    public function on(string $event, callable $callback)
    {
        $event = 'on' . ucfirst(strtolower($event));
        $this->$event = $callback;
    }

    private function emit(string $event, ...$param)
    {
        $event = 'on' . ucfirst(strtolower($event));
        if ($this->$event !== null) {
            call_user_func($this->$event, ...$param);
        }
    }

    public function __destruct()
    {
        $this->emit('stop');
    }
}
