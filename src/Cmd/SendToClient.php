<?php

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\CmdInterface;
use Xielei\Swoole\Gateway;

class SendToClient implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 21;
    }

    public static function encode(int $fd, string $message): string
    {
        return pack('CN', SELF::getCommandCode(), $fd) . $message;
    }

    public static function decode(string $buffer): array
    {
        $res = unpack('Nfd', $buffer);
        $res['message'] = substr($buffer, 4);
        return $res;
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer): bool
    {
        $data = self::decode($buffer);
        $gateway->sendToClient($data['fd'], $data['message']);
        return true;
    }
}
