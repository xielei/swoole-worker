<?php

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class GetClientCountByGroup implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 5;
    }

    public static function encode(string $group): string
    {
        return pack('C', SELF::getCommandCode()) . $group;
    }

    public static function decode(string $buffer): array
    {
        return [
            'group' => $buffer,
        ];
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $data = self::decode($buffer);
        $buffer = pack('N', count($gateway->group_list[$data['group']]));
        $conn->send(pack('N', 4 + strlen($buffer)) . $buffer);
        return true;
    }
}
