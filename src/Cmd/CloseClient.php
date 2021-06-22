<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;

class CloseClient implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 2;
    }

    public static function encode(int $fd, bool $force = false): string
    {
        return pack('CNC', self::getCommandCode(), $fd, $force);
    }

    public static function decode(string $buffer): array
    {
        return unpack('Nfd/Cforce', $buffer);
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer)
    {
        $data = self::decode($buffer);
        if ($gateway->getServer()->exist($data['fd'])) {
            $gateway->getServer()->close($data['fd'], (bool)$data['force']);
        }
    }
}
