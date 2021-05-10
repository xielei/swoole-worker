<?php

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class GetClientCount implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 4;
    }

    public static function encode(): string
    {
        return pack('C', SELF::getCommandCode());
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        if ($gateway->worker_id == 0) {
            $stats = $gateway->stats();
            $conn->send(pack('NN', 8, $stats['connection_num']));
        } else {
            $conn->send(pack('NN', 8, 0));
        }
        return true;
    }
}
