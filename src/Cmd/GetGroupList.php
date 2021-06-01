<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;
use Xielei\Swoole\Protocol;

class GetGroupList implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 11;
    }

    public static function encode(): string
    {
        return pack('C', self::getCommandCode());
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer)
    {
        $buffer = '';
        foreach (array_keys($gateway->group_list) as $group) {
            $buffer .= pack('C', strlen((string)$group)) . $group;
        }
        $conn->send(Protocol::encode($buffer));
    }
}
