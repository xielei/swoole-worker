<?php

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class SendToGroup implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 22;
    }

    public static function encode(string $group, string $message, array $without_fd_list): string
    {
        return pack('CCC', SELF::getCommandCode(), strlen($group), strlen($message)) . $group . $message . pack('N*', $without_fd_list);
    }

    public static function decode(string $buffer): array
    {
        $tmp = unpack('Cgroup_len/Cmessage_len', $buffer);
        return [
            'group' => substr($buffer, 2, $tmp['group_len']),
            'message' => substr($buffer, 2 + $tmp['group_len'], $tmp['message_len']),
            'without_fd_list' => unpack('N*', substr($buffer, 2 + $tmp['group_len'] + $tmp['message_len'])),
        ];
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $data = self::decode($buffer);
        $fd_list = $gateway->group_list[$data['group']] ?? [];
        foreach ($fd_list as $fd) {
            if (!in_array($fd, $data['without_fd_list'])) {
                $gateway->sendToClient($fd, $data['message']);
            }
        }
        return true;
    }
}
