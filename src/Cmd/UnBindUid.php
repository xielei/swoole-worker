<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;

class UnBindUid implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 24;
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
        if (isset($gateway->fd_list[$data['fd']])) {
            if ($bind_uid = $gateway->fd_list[$data['fd']]['uid']) {
                unset($gateway->uid_list[$bind_uid][$data['fd']]);
                if (isset($gateway->uid_list[$bind_uid]) && !$gateway->uid_list[$bind_uid]) {
                    unset($gateway->uid_list[$bind_uid]);
                }
            }
            $gateway->fd_list[$data['fd']]['uid'] = '';
        }
    }
}
