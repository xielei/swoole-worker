<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;
use Xielei\Swoole\Protocol;

class GetUidCount implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 13;
    }

    public static function encode(bool $read_uid_list = true): string
    {
        return pack('CC', self::getCommandCode(), $read_uid_list);
    }

    public static function decode($buffer)
    {
        return unpack('Cread_uid_list', $buffer);
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer)
    {
        $param = self::decode($buffer);
        $count = count($gateway->uid_list);

        if ($param['read_uid_list']) {
            if ($count > 100) {
                $uid_list = array_slice($gateway->uid_list, random_int(0, $count - 100), 100);
            } else {
                $uid_list = $gateway->uid_list;
            }
        } else {
            $uid_list = [];
        }

        $buffer = '';
        foreach (array_keys($uid_list) as $uid) {
            $buffer .= pack('C', strlen((string) $uid)) . $uid;
        }

        $conn->send(Protocol::encode(pack('N', $count) . $buffer));
    }
}
