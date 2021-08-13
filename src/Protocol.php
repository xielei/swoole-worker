<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Exception;

class Protocol
{
    const PING = 0;
    const GATEWAY_CONNECT = 1;
    const WORKER_CONNECT = 2;
    const BROADCAST_GATEWAY_LIST = 3;

    const EVENT_CONNECT = 20;
    const EVENT_RECEIVE = 21;
    const EVENT_CLOSE = 22;
    const EVENT_OPEN = 23;
    const EVENT_MESSAGE = 24;

    const CLIENT_INFO_REMOTE_IP = 0b00000001;
    const CLIENT_INFO_REMOTE_PORT = 0b00000010;
    const CLIENT_INFO_UID = 0b00000100;
    const CLIENT_INFO_SESSION = 0b00001000;
    const CLIENT_INFO_GROUP_LIST = 0b00010000;
    const CLIENT_INFO_SYSTEM = 0b00100000;

    public static function encode(string $load = ''): string
    {
        return pack('NN', 8 + strlen($load), crc32($load)) . $load;
    }

    public static function decode(string $buffer): string
    {
        $tmp = unpack('Nlen/Ncrc', $buffer);
        if ($tmp['len'] !== strlen($buffer)) {
            $hex = bin2hex($buffer);
            throw new Exception("protocol decode failure! buffer:{$hex}");
        }
        $load = substr($buffer, 8);
        if ($tmp['crc'] !== crc32($load)) {
            $hex = bin2hex($buffer);
            throw new Exception("protocol decode failure! buffer:{$hex}");
        }
        return $load;
    }
}
