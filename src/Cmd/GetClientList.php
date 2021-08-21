<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;
use Xielei\Swoole\Protocol;

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

    public static function execute(Gateway $gateway, Connection $conn, string $buffer)
    {
        $data = self::decode($buffer);
        if (!$fd_list = $gateway->getServer()->getClientList($data['prev_fd'], $data['limit'])) {
            $fd_list = [];
        }
        $conn->send(Protocol::encode(pack('N*', ...array_values($fd_list))));
    }
}
