<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;

class SetSession implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 23;
    }

    public static function encode(int $fd, array $session): string
    {
        return pack('CN', self::getCommandCode(), $fd) . serialize($session);
    }

    public static function decode(string $buffer): array
    {
        $data = unpack('Nfd', $buffer);
        $data['session'] = unserialize(substr($buffer, 4));
        return $data;
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer)
    {
        $data = self::decode($buffer);
        if (isset($gateway->fd_list[$data['fd']])) {
            $gateway->fd_list[$data['fd']]['session'] = $data['session'];
        }
    }
}
