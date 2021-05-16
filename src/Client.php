<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Swoole\Coroutine;
use Swoole\Coroutine\Client as CoClient;

class Client
{
    public $onStart;
    public $onConnect;
    public $onMessage;
    public $onClose;
    public $onError;
    public $onStop;

    private $conn = null;
    private $host = null;
    private $port = null;

    public function __construct(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;

        $conn = new CoClient(SWOOLE_SOCK_TCP);
        $conn->set([
            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 0,
        ]);
        $this->conn = $conn;
    }

    public function start()
    {
        $this->emit('start');
        $this->connect();
        Coroutine::create(function () {
            while (true) {
                if ($this->conn->isConnected()) {
                    $buffer = $this->conn->recv();
                    if ($buffer === '') {
                        $this->conn->close(true);
                        $this->emit('close');
                    } elseif ($buffer === false) {
                        $errCode = $this->conn->errCode;
                        $this->emit('error', $errCode);
                        if ($errCode !== SOCKET_ETIMEDOUT) {
                            $this->conn->close(true);
                            $this->emit('close');
                        }
                    } elseif ($buffer) {
                        $this->emit('message', $buffer);
                    } else {
                        $this->conn->close(true);
                        $this->emit('close');
                    }
                    Coroutine::sleep(0.001);
                } else {
                    Coroutine::sleep(1);
                }
            }
        });
    }

    public function send(string $buffer): bool
    {
        return strlen($buffer) == $this->conn->send($buffer);
    }

    public function sendAndRecv(string $buffer)
    {
        $res = $this->conn->send($buffer);
        if (strlen($buffer) === $res) {
            return $this->conn->recv();
        } else {
            return null;
        }
    }

    public function connect($timeout = 1)
    {
        if (!$this->conn->connect($this->host, $this->port, $timeout)) {
            $this->conn->close(true);
            $this->emit('close');
        } else {
            $this->emit('connect');
        }
    }

    public function close(bool $reset = false)
    {
        if ($this->conn->isConnected()) {
            $this->conn->close($reset);
        }
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
        $this->close(true);
        $this->emit('stop');
    }
}
