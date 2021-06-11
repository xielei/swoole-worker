<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;
use Xielei\Swoole\Protocol;

class GetClientInfo implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 7;
    }

    public static function encode(int $fd = 0, int $type = 0): string
    {
        return pack('CNC', self::getCommandCode(), $fd, $type);
    }

    public static function decode(string $buffer): array
    {
        return unpack('Nfd/Ctype', $buffer);
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer)
    {
        $data = self::decode($buffer);
        if (isset($gateway->fd_list[$data['fd']])) {
            $load = self::encodeClientBuffer($gateway, $data['fd'], $data['type']);
        } else {
            $load = '';
        }
        $conn->send(Protocol::encode(pack('C', $data['type']) . $load));
    }

    public static function result(string $buffer): ?array
    {
        $data = unpack('Ctype', $buffer);
        $load = substr($buffer, 1);
        if ($load) {
            return self::decodeClientBuffer($load, $data['type']);
        } else {
            return null;
        }
    }

    public static function encodeClientBuffer(Gateway $gateway, int $fd, int $type): string
    {
        $load = '';
        if (Protocol::CLIENT_INFO_UID & $type) {
            $uid = $gateway->fd_list[$fd]['uid'];
            $load .= pack('n', strlen((string) $uid)) . $uid;
        }
        if (Protocol::CLIENT_INFO_SESSION & $type) {
            $session = serialize($gateway->fd_list[$fd]['session']);
            $load .= pack('N', strlen($session)) . $session;
        }
        if (Protocol::CLIENT_INFO_GROUP_LIST & $type) {
            $load .= pack('n', count($gateway->fd_list[$fd]['group_list']));
            foreach ($gateway->fd_list[$fd]['group_list'] as $value) {
                $load .= pack('n', strlen((string) $value)) . $value;
            }
        }
        if (Protocol::CLIENT_INFO_REMOTE_IP & $type) {
            $load .= pack('N', ip2long($gateway->getServer()->getClientInfo($fd)['remote_ip']));
        }
        if (Protocol::CLIENT_INFO_REMOTE_PORT & $type) {
            $load .= pack('n', $gateway->getServer()->getClientInfo($fd)['remote_port']);
        }
        if (Protocol::CLIENT_INFO_SYSTEM & $type) {
            $info = serialize($gateway->getServer()->getClientInfo($fd));
            $load .= pack('N', strlen($info)) . $info;
        }
        return $load;
    }

    public static function decodeClientBuffer(string &$buffer, int $type): array
    {
        $res = [];
        if ($type & Protocol::CLIENT_INFO_UID) {
            $t = unpack('nlen', $buffer);
            $buffer = substr($buffer, 2);
            $res['uid'] = substr($buffer, 0, $t['len']);
            $buffer = substr($buffer, $t['len']);
        }
        if ($type & Protocol::CLIENT_INFO_SESSION) {
            $t = unpack('Nlen', $buffer);
            $buffer = substr($buffer, 4);
            $res['session'] = unserialize(substr($buffer, 0, $t['len']));
            $buffer = substr($buffer, $t['len']);
        }
        if ($type & Protocol::CLIENT_INFO_GROUP_LIST) {
            $t = unpack('ncount', $buffer);
            $buffer = substr($buffer, 2);
            $group_list = [];
            for ($i = 0; $i < $t['count']; $i++) {
                $q = unpack('nlen', $buffer);
                $buffer = substr($buffer, 2);
                $group_list[] = substr($buffer, 0, $q['len']);
                $buffer = substr($buffer, $q['len']);
            }
            $res['group_list'] = $group_list;
        }
        if ($type & Protocol::CLIENT_INFO_REMOTE_IP) {
            $res += unpack('Nremote_ip', $buffer);
            $res['remote_ip'] = long2ip($res['remote_ip']);
            $buffer = substr($buffer, 4);
        }
        if ($type & Protocol::CLIENT_INFO_REMOTE_PORT) {
            $res += unpack('nremote_port', $buffer);
            $buffer = substr($buffer, 2);
        }
        if ($type & Protocol::CLIENT_INFO_SYSTEM) {
            $t = unpack('Nlen', $buffer);
            $res['system'] = unserialize(substr($buffer, 4, $t['len']));
            $buffer = substr($buffer, 4 + $t['len']);
        }
        return $res;
    }
}
