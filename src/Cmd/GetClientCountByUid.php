<?php

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class GetClientCountByUid implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 6;
    }

    public static function encode(string $uid): string
    {
        return pack('C', SELF::getCommandCode()) . $uid;
    }

    public static function decode(string $buffer): array
    {
        return [
            'uid' => $buffer,
        ];
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $data = self::decode($buffer);
        $buffer = pack('N', count($gateway->uid_list[$data['uid']] ?? []));
        $conn->send(pack('N', 4 + strlen($buffer)) . $buffer);
        return true;
    }
}
