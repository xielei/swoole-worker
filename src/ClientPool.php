<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Swoole\ConnectionPool;
use Swoole\Coroutine\Socket;

class ClientPool extends ConnectionPool
{
    public function __construct(string $host, int $port, int $size = 1024)
    {
        $constructor = function () use ($host, $port): Socket {
            $conn = new Socket(AF_INET, SOCK_STREAM, 0);
            $conn->setProtocol([
                'open_length_check' => true,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 0,
            ]);
            if (!$conn->connect($host, $port)) {
                $conn->close(true);
            }
            return $conn;
        };

        parent::__construct($constructor, $size);
    }

    public function getConn()
    {
        $conn = $this->get();
        if (!$conn->checkLiveness()) {
            $this->num -= 1;
            $conn = $this->getConn();
        }
        return $conn;
    }

    public function send(string $buffer): bool
    {
        $conn = $this->getConn();
        $res = $conn->send($buffer);
        if (strlen($buffer) === $res) {
            $this->put($conn);
            return true;
        } else {
            $conn->close(true);
            $this->num -= 1;
            return false;
        }
    }

    public function sendAndRecv($buffer): ?string
    {
        $conn = $this->getConn();
        $res = $conn->send($buffer);
        if (strlen($buffer) === $res) {
            $recv = $conn->recv();
            if ($recv === '') {
                $conn->close(true);
                $this->num -= 1;
                return null;
            } elseif ($recv === false) {
                if ($conn->errCode !== SOCKET_ETIMEDOUT) {
                    $conn->close(true);
                    $this->num -= 1;
                    return null;
                } else {
                    $this->put($conn);
                    return null;
                }
            } else {
                $this->put($conn);
                return $recv;
            }
        } else {
            $conn->close(true);
            $this->num -= 1;
            return null;
        }
    }
}
