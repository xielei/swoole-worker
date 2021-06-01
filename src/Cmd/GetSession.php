<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;
use Xielei\Swoole\Protocol;

class GetSession implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 12;
    }

    public static function encode(int $fd): string
    {
        return pack('CN', self::getCommandCode(), $fd);
    }

    public static function decode(string $buffer): array
    {
        return unpack('Nfd', $buffer);
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer)
    {
        $data = self::decode($buffer);
        $buffer = serialize($gateway->fd_list[$data['fd']]['session'] ?? null);
        $conn->send(Protocol::encode($buffer));
    }
}
