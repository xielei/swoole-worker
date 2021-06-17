<?php

declare(strict_types=1);

namespace Xielei\Swoole\Library;

use Swoole\ConnectionPool;
use Swoole\Coroutine;
use Swoole\Coroutine\Client as CoroutineClient;
use Xielei\Swoole\Service;

class Client
{
    public $onStart;
    public $onConnect;
    public $onMessage;
    public $onClose;
    public $onError;
    public $onStop;

    private $host = null;
    private $port = null;

    private $pool = null;
    private $stoped = false;

    public function __construct(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;

        $this->pool = new ConnectionPool(function () use ($host, $port) {
            $conn = new CoroutineClient(SWOOLE_SOCK_TCP);
            $conn->set([
                'open_length_check' => true,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 0,
            ]);
            Service::debug("create client {$host}:{$port}");
            return $conn;
        }, 1);
    }

    public function start()
    {
        Coroutine::create(function () {
            $this->emit('start');
            $this->connect();
            while (!$this->stoped) {
                $conn = $this->pool->get();
                $buffer = $conn->recv(1);
                if ($buffer === '') {
                    $conn->close(true);
                    $this->pool->put($conn);
                    Service::debug("close2 {$this->host}:{$this->port}");
                    $this->emit('close');
                } elseif ($buffer === false) {
                    $errCode = $conn->errCode;
                    if ($errCode !== SOCKET_ETIMEDOUT) {
                        $conn->close(true);
                        $this->pool->put($conn);
                        $this->emit('error', $errCode);
                        Service::debug("close3 {$this->host}:{$this->port}");
                        $this->emit('close');
                    } else {
                        $this->pool->put($conn);
                        $this->emit('error', $errCode);
                    }
                } elseif ($buffer) {
                    $this->pool->put($conn);
                    $this->emit('message', $buffer);
                } else {
                    $conn->close(true);
                    $this->pool->put($conn);
                    Service::debug("close4 {$this->host}:{$this->port}");
                    $this->emit('close');
                }
            }
            $conn = $this->pool->get();
            if ($conn->isConnected()) {
                $conn->close();
            }
            $this->pool->close();
            $this->emit('close');
            $this->emit('stop');
        });
    }

    public function send(string $buffer)
    {
        if (!$this->stoped) {
            $conn = $this->pool->get();
            $res = strlen($buffer) === $conn->send($buffer);
            $this->pool->put($conn);
            return $res;
        }
    }

    public function sendAndRecv(string $buffer, float $timeout = 1)
    {
        if (!$this->stoped) {
            $conn = $this->pool->get();
            $len = $conn->send($buffer);
            if (strlen($buffer) === $len) {
                $res = $conn->recv($timeout);
                if ($res === '') {
                    $conn->close();
                }
                $this->pool->put($conn);
                return $res;
            } else {
                $this->pool->put($conn);
            }
        }
    }

    public function connect(float $timeout = 1)
    {
        if (!$this->stoped) {
            $conn = $this->pool->get();
            if (false === $conn->connect($this->host, $this->port, $timeout)) {
                $conn->close(true);
                $this->pool->put($conn);
                Service::debug("close1 {$this->host}:{$this->port}");
                $this->emit('close');
            } else {
                Service::debug("connect success {$this->host}:{$this->port}");
                $this->pool->put($conn);
                $this->emit('connect');
            }
        }
    }

    public function on(string $event, callable $callback)
    {
        $event = 'on' . ucfirst(strtolower($event));
        $this->$event = $callback;
    }

    public function stop()
    {
        $this->stoped = true;
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
        $this->stoped = true;
    }
}
