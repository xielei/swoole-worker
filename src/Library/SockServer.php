<?php

declare(strict_types=1);

namespace Xielei\Swoole\Library;

use Swoole\ConnectionPool;
use Swoole\Coroutine;
use Swoole\Coroutine\Server\Connection;
use Swoole\Process;
use Swoole\Coroutine\Server;
use Swoole\Coroutine\Client;
use Xielei\Swoole\Protocol;

class SockServer
{
    private $sock_file;
    private $callback;
    private $pool;

    public function __construct(callable $callback, string $sock_file = null)
    {
        $this->sock_file = $sock_file ?: ('/var/run/' . uniqid() . '.sock');
        $this->callback = $callback;
        $this->pool = new ConnectionPool(function () {
            $client = new Client(SWOOLE_UNIX_STREAM);
            $client->set([
                'open_length_check' => true,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 0,
            ]);
            connect:
            if (!$client->connect($this->sock_file)) {
                $client->close();
                Coroutine::sleep(0.001);
                goto connect;
            }
            return $client;
        }, 1);
    }

    public function mountTo(\Swoole\Server $server)
    {
        $server->addProcess(new Process(function (Process $process) {
            $this->startLanServer();
            while (true) {
                Coroutine::sleep(1000);
            }
        }, false, 2, true));
    }

    public function getSockFile(): string
    {
        return $this->sock_file;
    }

    public function sendAndReceive($data)
    {
        $client = $this->pool->get();
        $client->send(Protocol::encode(serialize($data)));
        $res = unserialize(Protocol::decode($client->recv()));
        $this->pool->put($client);
        return $res;
    }

    private function startLanServer()
    {
        $server = new Server('unix:' . $this->sock_file);
        $server->set([
            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 0,
        ]);
        $server->handle(function (Connection $conn) {
            while (true) {
                $buffer = $conn->recv(1);
                if ($buffer === '') {
                    $conn->close();
                    break;
                } elseif ($buffer === false) {
                    if (swoole_last_error() !== SOCKET_ETIMEDOUT) {
                        $conn->close();
                        break;
                    }
                } else {
                    $res = unserialize(Protocol::decode($buffer));
                    call_user_func($this->callback ?: function () {
                    }, $conn, $res);
                }
                Coroutine::sleep(0.001);
            }
        });
        $server->start();
    }

    public static function sendToConn(Connection $conn, $data)
    {
        $conn->send(Protocol::encode(serialize($data)));
    }
}
