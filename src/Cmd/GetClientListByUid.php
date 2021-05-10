<?php

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class GetClientListByUid implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 10;
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
        $fd_list = $gateway->uid_list[$data['uid']] ?? [];
        $conn->send(pack('NN*', 4 + 4 * count($fd_list), ...$fd_list));
        return true;
    }
}
