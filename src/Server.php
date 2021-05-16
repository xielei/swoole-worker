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
            'tcp_keepidle' => 1, //4s没有数据传输就进行检测
            'tcp_keepinterval' => 1, //1s探测一次
            'tcp_keepcount' => 5, //探测的次数，超过5次后还没回包close此连接

            'heartbeat_idle_time' => 5, // 表示一个连接如果60秒内未向服务器发送任何数据，此连接将被强制关闭
            'heartbeat_check_interval' => 1, // 表示每6秒遍历一次
        ]);

        //接收到新的连接请求 并自动创建一个协程
        $server->handle(function (Connection $conn) {
            $this->emit('connect', $conn);
            while (true) {
                //接收数据
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
