<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;
use Xielei\Swoole\Protocol;

class IsOnline implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 16;
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
        $is_online = $gateway->getServer()->exist($data['fd']);
        $buffer = pack('C', $is_online);
        $conn->send(Protocol::encode($buffer));
    }

    public static function result(string $buffer): bool
    {
        return unpack('Cis_online', $buffer)['is_online'] ? true : false;
    }
}
