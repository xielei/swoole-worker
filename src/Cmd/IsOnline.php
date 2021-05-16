<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

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

    public static function decode(string $buffer): ?array
    {
        return unpack('Nfd', $buffer) ?: null;
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        if (!$data = self::decode($buffer)) {
            return false;
        }
        $is_online = $gateway->exist($data['fd']);
        $buffer = pack('C', $is_online);
        $conn->send(pack('N', 4 + strlen($buffer)) . $buffer);
    }

    public static function result(string $buffer): ?bool
    {
        if ($tmp = unpack('Nlen/Cis_online', $buffer)) {
            return $tmp['is_online'] ? true : false;
        }
        return null;
    }
}
