<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;

class UnGroup implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 25;
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
        if (isset($gateway->group_list[$data['group']])) {
            foreach ($gateway->group_list[$data['group']] as $fd) {
                unset($gateway->fd_list[$fd]['group_list'][$data['group']]);
            }
            unset($gateway->group_list[$data['group']]);
        }
    }
}
