<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class GetUidList implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 14;
    }

    public static function encode(): string
    {
        return pack('C', self::getCommandCode());
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $buffer = '';
        foreach ($gateway->uid_list as $uid => $fd_list) {
            $buffer .= pack('C', strlen((string)$uid)) . $uid;
        }

        $conn->send(pack('N', 4 + strlen($buffer)) . $buffer);

        return true;
    }
}
