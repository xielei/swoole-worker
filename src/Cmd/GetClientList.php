<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class GetClientList implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 8;
    }

    public static function encode(int $limit = 100, int $prev_fd = 0): string
    {
        return pack('CNN', self::getCommandCode(), $limit, $prev_fd);
    }

    public static function decode(string $buffer): array
    {
        return unpack('Nlimit/Nprev_fd', $buffer);
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $data = self::decode($buffer);
        $fd_list = [];
        if ($limit = $data['limit']) {
            foreach ($gateway->fd_list as $fd => $value) {
                if ($fd > $data['prev_fd']) {
                    $fd_list[] = $fd;
                    $limit -= 1;
                    if (!$limit) {
                        break;
                    }
                }
            }
        }
        $conn->send(pack('NN*', 4 + 4 * count($fd_list), ...$fd_list));
        return true;
    }
}
