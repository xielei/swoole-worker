<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\ConnectionPool;
use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class RegisterWorker implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 19;
    }

    public static function encode(): string
    {
        return pack('C', self::getCommandCode());
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $address = implode(':', $conn->exportSocket()->getpeername());
        $gateway->worker_pool_list[$address] = new ConnectionPool(function () use ($conn) {
            return $conn;
        }, 1);
        return true;
    }
}
