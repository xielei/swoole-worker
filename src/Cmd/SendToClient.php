<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;

class SendToClient implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 21;
    }

    public static function encode(int $fd, string $message): string
    {
        return pack('CN', self::getCommandCode(), $fd) . $message;
    }

    public static function decode(string $buffer): array
    {
        $res = unpack('Nfd', $buffer);
        $res['message'] = substr($buffer, 4);
        return $res;
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer)
    {
        $data = self::decode($buffer);
        $gateway->sendToClient($data['fd'], $data['message']);
    }
}
