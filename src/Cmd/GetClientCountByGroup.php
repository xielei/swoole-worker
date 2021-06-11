<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;
use Xielei\Swoole\Protocol;

class GetClientCountByGroup implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 5;
    }

    public static function encode(string $group): string
    {
        return pack('C', self::getCommandCode()) . $group;
    }

    public static function decode(string $buffer): array
    {
        return [
            'group' => $buffer,
        ];
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer)
    {
        $data = self::decode($buffer);
        $buffer = pack('N', count($gateway->group_list[$data['group']] ?? []));
        $conn->send(Protocol::encode($buffer));
    }
}
