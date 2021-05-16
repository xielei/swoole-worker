<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class DeleteSession implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 3;
    }

    public static function encode(int $fd): string
    {
        return pack('CN', self::getCommandCode(), $fd);
    }

    public static function decode(string $buffer): array
    {
        return unpack('Nfd', $buffer);
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $data = self::decode($buffer);
        if ($gateway->exist($data['fd'])) {
            $gateway->fd_list[$data['fd']]['session'] = [];
        }
        return true;
    }
}
