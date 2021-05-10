<?php

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class GetGroupList implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 11;
    }

    public static function encode(): string
    {
        return pack('C', SELF::getCommandCode());
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $buffer = '';
        foreach (array_keys($gateway->group_list) as $group) {
            $buffer .= pack('C', strlen($group)) . $group;
        }
        $conn->send(pack('N', 4 + strlen($buffer)) . $buffer);
        return true;
    }
}
