<?php

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class Ping implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 0;
    }

    public static function encode(): string
    {
        return pack('C', SELF::getCommandCode());
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        return true;
    }
}
