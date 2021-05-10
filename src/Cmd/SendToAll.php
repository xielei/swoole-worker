<?php

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class SendToAll implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 20;
    }

    public static function encode(string $message, array $without_fd_list): string
    {
        return pack('CC', SELF::getCommandCode(), strlen($message)) . $message . pack('N*', $without_fd_list);
    }

    public static function decode(string $buffer): array
    {
        $tmp = unpack('Cmessage_len', $buffer);
        return [
            'message' => substr($buffer, 1, $tmp['message_len']),
            'without_fd_list' => unpack('N*', substr($buffer, 1 + $tmp['message_len'])),
        ];
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $data = self::decode($buffer);
        foreach ($gateway->ports[$gateway->worker_id]->connections as $fd) {
            if (!in_array($fd, $data['without_fd_list'])) {
                $gateway->sendToClient($fd, $data['message']);
            }
        }
        return true;
    }
}
