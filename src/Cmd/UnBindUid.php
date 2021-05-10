<?php

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class UnBindUid implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 24;
    }

    public static function encode(int $fd): string
    {
        return pack('CN', SELF::getCommandCode(), $fd);
    }

    public static function decode(string $buffer): array
    {
        return unpack('Nfd', $buffer);
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $data = self::decode($buffer);
        if ($bind_uid = $gateway->fd_list[$data['fd']]['uid']) {
            unset($gateway->uid_list[$bind_uid][$data['fd']]);
            if (isset($gateway->uid_list[$bind_uid]) && !$gateway->uid_list[$bind_uid]) {
                unset($gateway->uid_list[$bind_uid]);
            }
        }
        $gateway->fd_list[$data['fd']]['uid'] = null;
        return true;
    }
}
