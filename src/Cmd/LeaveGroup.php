<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class LeaveGroup implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 18;
    }

    public static function encode(int $fd, string $group): string
    {
        return pack('CN', self::getCommandCode(), $fd) . $group;
    }

    public static function decode(string $buffer): array
    {
        $res = unpack('Nfd', $buffer);
        $res['group'] = substr($buffer, 4);
        return $res;
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $data = self::decode($buffer);
        unset($gateway->group_list[$data['group']][$data['fd']]);
        if (isset($gateway->group_list[$data['group']]) && !$gateway->group_list[$data['group']]) {
            unset($gateway->group_list[$data['group']]);
        }
        unset($gateway->fd_list[$data['fd']]['group_list'][$data['group']]);
        return true;
    }
}
