<?php

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class BindUid implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 1;
    }

    public static function encode(int $fd, string $uid): string
    {
        return pack('CN', SELF::getCommandCode(), $fd) . $uid;
    }

    public static function decode(string $buffer): array
    {
        $res = unpack('Nfd', $buffer);
        $res['uid'] = substr($buffer, 4);
        return $res;
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $data = self::decode($buffer);
        if (!isset($gateway->uid_list[$data['uid']])) {
            $gateway->uid_list[$data['uid']] = [];
        }

        if ($old_bind_uid = $gateway->fd_list[$data['fd']]['uid']) {
            unset($gateway->uid_list[$old_bind_uid][$data['fd']]);
            if (isset($gateway->uid_list[$old_bind_uid]) && !$gateway->uid_list[$old_bind_uid]) {
                unset($gateway->uid_list[$old_bind_uid]);
            }
        }

        $gateway->fd_list[$data['fd']]['uid'] = $data['uid'];
        $gateway->uid_list[$data['uid']][$data['fd']] = $data['fd'];

        return true;
    }
}
