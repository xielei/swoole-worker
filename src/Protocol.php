<?php

declare(strict_types=1);

namespace Xielei\Swoole;

class Protocol
{
    const PING = 0;

    // gateway链接register
    const GATEWAY_CONNECT = 1;

    // worker链接register
    const WORKER_CONNECT = 2;

    // 给worker广播gateway内部连接地址
    const BROADCAST_ADDRESS_LIST = 3;

    // 发给worker，gateway有一个新的连接
    const CLIENT_CONNECT = 10;

    // 当websocket握手时触发，只有websocket协议支持此命令
    const CLIENT_WEBSOCKET_CONNECT = 11;

    // 发给worker的，客户端有消息
    const CLIENT_MESSAGE = 12;

    // 发给worker上的关闭链接事件
    const CLIENT_CLOSE = 13;

    const CLIENT_INFO_REMOTE_IP = 0b00000001;
    const CLIENT_INFO_REMOTE_PORT = 0b00000010;
    const CLIENT_INFO_UID = 0b00000100;
    const CLIENT_INFO_SESSION = 0b00001000;
    const CLIENT_INFO_GROUP_LIST = 0b00010000;
    const CLIENT_INFO_TAG_LIST = 0b00100000;
    const CLIENT_INFO_SYSTEM = 0b10000000;

    public static function encode($cmd, array $data = []): string
    {
        $load = '';
        switch ($cmd) {
            case self::PING:
                break;

            case self::GATEWAY_CONNECT:
                $load .= pack('Nn', $data['lan_host'], $data['lan_port']) . $data['register_secret_key'];
                break;

            case self::WORKER_CONNECT:
                $load .= $data['register_secret_key'];
                break;

            case self::BROADCAST_ADDRESS_LIST:
                $load .= $data['addresses'];
                break;

            case self::CLIENT_CONNECT:
                $session_string = serialize($data['session']);
                $load .= pack(
                    'NN',
                    $data['fd'],
                    strlen($session_string)
                ) . $session_string;
                break;

            case self::CLIENT_WEBSOCKET_CONNECT:
                $session_string = serialize($data['session']);
                $load .= pack(
                    'NN',
                    $data['fd'],
                    strlen($session_string)
                ) . $session_string . serialize($data['global']);
                break;

            case self::CLIENT_MESSAGE:
                $session_string = serialize($data['session']);
                $message = $data['message'] ?? null;
                $load .= pack(
                    'NN',
                    $data['fd'],
                    strlen($session_string)
                ) . $session_string . $message;
                break;

            case self::CLIENT_CLOSE:
                $session_string = serialize($data['session']);
                $load .= pack(
                    'NN',
                    $data['fd'],
                    strlen($session_string)
                ) . $session_string . serialize($data['bind']);
                break;

            default:
                break;
        }
        return pack('NC', 5 + strlen($load), $cmd) . $load;
    }

    public static function decode($buffer): array
    {
        $data = unpack('Npack_len/Ccmd', $buffer);
        $load = substr($buffer, 5);
        switch ($data['cmd']) {

            case self::PING:
                break;

            case self::GATEWAY_CONNECT:
                $data += unpack('Nlan_host/nlan_port', $load);
                $data['register_secret_key'] = substr($load, 6);
                break;

            case self::WORKER_CONNECT:
                $data['register_secret_key'] = $load;
                break;

            case self::BROADCAST_ADDRESS_LIST:
                $addresses = [];
                if ($load && (strlen($load) % 6 === 0)) {
                    foreach (str_split($load, 6) as $value) {
                        $address = unpack('Nlan_host/nlan_port', $value);
                        $address['lan_host'] = long2ip($address['lan_host']);
                        $addresses[$address['lan_host'] . ':' . $address['lan_port']] = $address;
                    }
                }
                $data['addresses'] = $addresses;
                break;

            case self::CLIENT_CONNECT:
                $data += unpack('Nfd/Nsession_len', $load);
                $data['session'] = unserialize(substr($load, 8, $data['session_len']));
                unset($data['session_len']);
                break;

            case self::CLIENT_WEBSOCKET_CONNECT:
                $data += unpack('Nfd/Nsession_len', $load);
                $data['session'] = unserialize(substr($load, 8, $data['session_len']));
                $data['global'] = unserialize(substr($load, 8 + $data['session_len']));
                unset($data['session_len']);
                break;

            case self::CLIENT_MESSAGE:
                $data += unpack('Nfd/Nsession_len', $load);
                $data['session'] = unserialize(substr($load, 8, $data['session_len']));
                $data['message'] = substr($load, 8 + $data['session_len']);
                unset($data['session_len']);
                break;

            case self::CLIENT_CLOSE:
                $data += unpack('Nfd/Nsession_len', $load);
                $data['session'] = unserialize(substr($load, 8, $data['session_len']));
                $data['bind'] = unserialize(substr($load, 8 + $data['session_len']));
                unset($data['session_len']);
                break;

            default:
                break;
        }
        unset($data['pack_len']);
        return $data;
    }
}
