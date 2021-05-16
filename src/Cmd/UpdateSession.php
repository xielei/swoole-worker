<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class UpdateSession implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 26;
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

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $data = self::decode($buffer);
        if ($gateway->exist($data['fd'])) {
            $gateway->fd_list[$data['fd']]['session'] = array_merge($gateway->fd_list[$data['fd']]['session'] ?? [], $data['session']);
        }
        return true;
    }
}
