<?php

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class GetClientListByGroup implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 9;
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
        $fd_list = $gateway->group_list[$data['group']] ?? [];
        $conn->send(pack('NN*', 4 + 4 * count($fd_list), ...$fd_list));
        return true;
    }
}
