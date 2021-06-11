<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;

class SendToGroup implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 22;
    }

    public static function encode(string $group, string $message, array $without_fd_list = []): string
    {
        return pack('CCn', self::getCommandCode(), strlen($group), count($without_fd_list)) . $group . ($without_fd_list ? pack('N*', ...$without_fd_list) : '') . $message;
    }

    public static function decode(string $buffer): array
    {
        $tmp = unpack('Cgroup_len/ncount', $buffer);
        return [
            'group' => substr($buffer, 3, $tmp['group_len']),
            'without_fd_list' => unpack('N*', substr($buffer, 3 + $tmp['group_len'], $tmp['count'] * 4)),
            'message' => substr($buffer, 3 + $tmp['group_len'] + $tmp['count'] * 4),
        ];
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer)
    {
        $data = self::decode($buffer);
        $fd_list = $gateway->group_list[$data['group']] ?? [];
        foreach ($fd_list as $fd) {
            if (!in_array($fd, $data['without_fd_list'])) {
                $gateway->sendToClient($fd, $data['message']);
            }
        }
    }
}
