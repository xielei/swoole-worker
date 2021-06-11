<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;

class SendToAll implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 20;
    }

    public static function encode(string $message, array $without_fd_list = []): string
    {
        return pack('Cn', self::getCommandCode(), count($without_fd_list)) . ($without_fd_list ? pack('N*', ...$without_fd_list) : '') . $message;
    }

    public static function decode(string $buffer): array
    {
        $tmp = unpack('ncount', $buffer);
        return [
            'without_fd_list' => unpack('N*', substr($buffer, 2, $tmp['count'] * 4)),
            'message' => substr($buffer, 2 + $tmp['count'] * 4),
        ];
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer)
    {
        $data = self::decode($buffer);
        foreach ($gateway->fd_list as $fd => $info) {
            if (!in_array($fd, $data['without_fd_list'])) {
                $gateway->sendToClient($fd, $data['message']);
            }
        }
    }
}
