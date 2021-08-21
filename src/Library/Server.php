<?php

declare(strict_types=1);

namespace Xielei\Swoole\Library;

use Swoole\Coroutine;
use Swoole\Coroutine\Server as CoroutineServer;
use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Service;

class Server
{
    public $onStart;
    public $onConnect;
    public $onMessage;
    public $onClose;
    public $onError;
    public $onStop;

    private $server = null;
    private $stoped = false;

    public function __construct(string $host, int $port)
    {
        Service::debug("create server {$host}:{$port}");
        $server = new CoroutineServer($host, $port, false, true);
        $server->set([
            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 0,

            'heartbeat_idle_time' => 60,
            'heartbeat_check_interval' => 6,
        ]);

        $server->handle(function (Connection $conn) {
            $this->emit('connect', $conn);
            while (!$this->stoped) {
                $buffer = $conn->recv(1);
                if ($buffer === '') {
                    Service::debug("server close1");
                    $conn->close();
                    $this->emit('close', $conn);
                    break;
                } elseif ($buffer === false) {
                    $errCode = swoole_last_error();
                    $this->emit('error', $conn, $errCode);
                    if ($errCode !== SOCKET_ETIMEDOUT) {
                        $conn->close();
                        $this->emit('close', $conn);
                        break;
                    }
                } else {
                    $this->emit('message', $conn, $buffer);
                }
            }
            Service::debug("server close5");
            $conn->close();
            $this->emit('close', $conn);
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

    public function stop()
    {
        $this->stoped = true;
        $this->server->shutdown();
        $this->emit('stop');
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
}
