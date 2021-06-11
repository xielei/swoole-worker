<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;
use Xielei\Swoole\Protocol;

class GetUidListByGroup implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 15;
    }

    public static function encode(string $group): string
    {
        return pack('C', self::getCommandCode()) . $group;
    }

    public static function decode(string $buffer): array
    {
        return [
            'group' => $buffer,
        ];
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer)
    {
        $data = self::decode($buffer);

        $uid_list = [];
        foreach ($gateway->group_list[$data['group']] ?? [] as $fd) {
            if (isset($gateway->fd_list[$fd]['uid']) && $gateway->fd_list[$fd]['uid']) {
                $uid_list[] = $gateway->fd_list[$fd]['uid'];
            }
        }
        $uid_list = array_filter(array_unique($uid_list));

        $buffer = '';
        foreach ($uid_list as $uid) {
            $buffer .= pack('C', strlen((string) $uid)) . $uid;
        }

        $conn->send(Protocol::encode($buffer));
    }
}
