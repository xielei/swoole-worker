<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Exception;
use Xielei\Swoole\Cmd\BindUid;
use Xielei\Swoole\Cmd\CloseClient;
use Xielei\Swoole\Cmd\DeleteSession;
use Xielei\Swoole\Cmd\GetClientCount;
use Xielei\Swoole\Cmd\GetClientCountByGroup;
use Xielei\Swoole\Cmd\GetClientInfo;
use Xielei\Swoole\Cmd\GetClientList;
use Xielei\Swoole\Cmd\GetClientListByGroup;
use Xielei\Swoole\Cmd\GetClientListByUid;
use Xielei\Swoole\Cmd\GetGroupList;
use Xielei\Swoole\Cmd\GetSession;
use Xielei\Swoole\Cmd\GetUidCount;
use Xielei\Swoole\Cmd\GetUidList;
use Xielei\Swoole\Cmd\GetUidListByGroup;
use Xielei\Swoole\Cmd\IsOnline;
use Xielei\Swoole\Cmd\JoinGroup;
use Xielei\Swoole\Cmd\LeaveGroup;
use Xielei\Swoole\Cmd\SendToAll;
use Xielei\Swoole\Cmd\SendToClient;
use Xielei\Swoole\Cmd\SendToGroup;
use Xielei\Swoole\Cmd\SetSession;
use Xielei\Swoole\Cmd\UnBindUid;
use Xielei\Swoole\Cmd\UnGroup;
use Xielei\Swoole\Cmd\UpdateSession;

class HttpApi
{
    private $register_host = '127.0.0.1';
    private $register_port = 9327;
    private $register_secret_key = '';

    public function __construct(string $register_host = '127.0.0.1', int $register_port = 9327, string $register_secret_key = '')
    {
        $this->register_host = $register_host;
        $this->register_port = $register_port;
        $this->register_secret_key = $register_secret_key;
    }

    /**
     * 给客户端发消息
     *
     * @param string $client 客户端
     * @param string $message 消息内容
     * @return void
     */
    public function sendToClient(string $client, string $message)
    {
        $address = $this->clientToAddress($client);
        $this->sendToAddress($address, SendToClient::encode($address['fd'], $message));
    }

    /**
     * 给绑定了指定uid的客户端发消息
     *
     * @param string $uid uid
     * @param string $message 消息内容
     * @param array $without_client_list 要排除的客户端列表
     * @return void
     */
    public function sendToUid(string $uid, string $message, array $without_client_list = [])
    {
        foreach ($this->getClientListByUid($uid) as $client) {
            if (!in_array($client, $without_client_list)) {
                $this->sendToClient($client, $message);
            }
        }
    }

    /**
     * 给指定分组下的所有客户端发消息
     *
     * @param string $group 组名称
     * @param string $message 消息内容
     * @param array $without_client_list 要排除的客户端
     * @return void
     */
    public function sendToGroup(string $group, string $message, array $without_client_list = [])
    {
        foreach ($this->getAddressList() as $address) {
            $this->sendToAddress($address, SendToGroup::encode($group, $message, (function () use ($address, $without_client_list): array {
                $res = [];
                foreach ($without_client_list as $client) {
                    $tmp = $this->clientToAddress($client);
                    if ($tmp['lan_host'] == $address['lan_host'] && $tmp['lan_port'] == $address['lan_port']) {
                        $res[] = $tmp['fd'];
                    }
                }
                return $res;
            })()));
        }
    }

    /**
     * 给所有客户端发消息
     *
     * @param string $message 发送的内容
     * @param array $without_client_list 要排除的客户端
     * @return void
     */
    public function sendToAll(string $message, array $without_client_list = [])
    {
        foreach ($this->getAddressList() as $address) {
            $this->sendToAddress($address, SendToAll::encode($message, (function () use ($address, $without_client_list): array {
                $res = [];
                foreach ($without_client_list as $client) {
                    $tmp = $this->clientToAddress($client);
                    if ($tmp['lan_host'] == $address['lan_host'] && $tmp['lan_port'] == $address['lan_port']) {
                        $res[] = $tmp['fd'];
                    }
                }
                return $res;
            })()));
        }
    }

    /**
     * 判断指定客户端是否在线
     *
     * @param string $client
     * @return boolean
     */
    public function isOnline(string $client): bool
    {
        $address = $this->clientToAddress($client);
        return isOnline::result($this->sendToAddressAndRecv($address, IsOnline::encode($address['fd'])));
    }

    /**
     * 判断指定uid是否在线
     *
     * @param string $uid
     * @return boolean
     */
    public function isUidOnline(string $uid): bool
    {
        foreach ($this->getClientListByUid($uid) as $value) {
            return true;
        }
        return false;
    }

    /**
     * 获取指定分组下的客户列表
     *
     * @param string $group 分组名称
     * @param string $prev_client 从该客户端开始读取
     * @return iterable
     */
    public function getClientListByGroup(string $group, string $prev_client = null): iterable
    {
        $start = $prev_client ? false : true;
        if (!$start) {
            $tmp = $this->clientToAddress($prev_client);
            $prev_address = [
                'lan_host' => $tmp['lan_host'],
                'lan_port' => $tmp['lan_port'],
            ];
            $prev_fd = $tmp['fd'];
        }
        $buffer = GetClientListByGroup::encode($group);
        foreach ($this->getAddressList() as $address) {
            if ($start || $address == $prev_address) {
                $res = $this->sendToAddressAndRecv($address, $buffer);
                foreach (unpack('N*', $res) as $fd) {
                    if ($start || $fd > $prev_fd) {
                        $start = true;
                        yield $this->addressToClient([
                            'lan_host' => $address['lan_host'],
                            'lan_port' => $address['lan_port'],
                            'fd' => $fd,
                        ]);
                    }
                }
                $start = true;
            }
        }
    }

    /**
     * 获取所有客户端数量
     *
     * @return integer
     */
    public function getClientCount(): int
    {
        $items = [];
        $buffer = GetClientCount::encode();
        foreach ($this->getAddressList() as $key => $address) {
            $items[$key] = [
                'address' => $address,
                'buffer' => $buffer,
            ];
        }
        $buffers = $this->sendToAddressListAndRecv($items);

        $count = 0;
        foreach ($buffers as $key => $buffer) {
            $data = unpack('Nnum', $buffer);
            $count += $data['num'];
        }
        return $count;
    }

    /**
     * 获取指定分组下的客户端数量
     *
     * @param string $group
     * @return integer
     */
    public function getClientCountByGroup(string $group): int
    {
        $items = [];
        $buffer = GetClientCountByGroup::encode($group);
        foreach ($this->getAddressList() as $key => $address) {
            $items[$key] = [
                'address' => $address,
                'buffer' => $buffer,
            ];
        }
        $buffers = $this->sendToAddressListAndRecv($items);

        $count = 0;
        foreach ($buffers as $key => $buffer) {
            $data = unpack('Ncount', $buffer);
            $count += $data['count'];
        }
        return $count;
    }

    /**
     * 获取客户端列表
     *
     * @param string $prev_client 从该客户端开始读取
     * @return iterable
     */
    public function getClientList(string $prev_client = null): iterable
    {
        $start = $prev_client ? false : true;
        if (!$start) {
            $tmp = $this->clientToAddress($prev_client);
            $prev_address = [
                'lan_host' => $tmp['lan_host'],
                'lan_port' => $tmp['lan_port'],
            ];
            $prev_fd = $tmp['fd'];
        }
        foreach ($this->getAddressList() as $address) {
            if ($start || $address == $prev_address) {
                $tmp_prev_fd = 0;
                while ($fd_list = unpack('N*', $this->sendToAddressAndRecv($address, GetClientList::encode(10000, $tmp_prev_fd)))) {
                    foreach ($fd_list as $fd) {
                        if ($start || $fd > $prev_fd) {
                            $start = true;
                            yield $this->addressToClient([
                                'lan_host' => $address['lan_host'],
                                'lan_port' => $address['lan_port'],
                                'fd' => $fd,
                            ]);
                        }
                    }
                    $tmp_prev_fd = $fd;
                }
                $start = true;
            }
        }
    }

    /**
     * 获取某个uid下绑定的客户端列表
     *
     * @param string $uid
     * @param string $prev_client 从该客户但开始读取
     * @return iterable
     */
    public function getClientListByUid(string $uid, string $prev_client = null): iterable
    {
        $start = $prev_client ? false : true;
        if (!$start) {
            $tmp = $this->clientToAddress($prev_client);
            $prev_address = [
                'lan_host' => $tmp['lan_host'],
                'lan_port' => $tmp['lan_port'],
            ];
            $prev_fd = $tmp['fd'];
        }
        $buffer = GetClientListByUid::encode($uid);
        foreach ($this->getAddressList() as $address) {
            if ($start || $address == $prev_address) {
                $res = $this->sendToAddressAndRecv($address, $buffer);
                foreach (unpack('N*', $res) as $fd) {
                    if ($start || $fd > $prev_fd) {
                        $start = true;
                        yield $this->addressToClient([
                            'lan_host' => $address['lan_host'],
                            'lan_port' => $address['lan_port'],
                            'fd' => $fd,
                        ]);
                    }
                }
                $start = true;
            }
        }
    }

    /**
     * 获取客户信息
     *
     * @param string $client 客户端
     * @param integer $type 具体要获取哪些数据，默认全部获取，也可按需获取，可选参数：Xielei\Swoole\Protocol::CLIENT_INFO_UID(绑定的uid) | Xielei\Swoole\Protocol::CLIENT_INFO_SESSION(session) | Xielei\Swoole\Protocol::CLIENT_INFO_GROUP_LIST(绑定的分组列表) | Xielei\Swoole\Protocol::CLIENT_INFO_REMOTE_IP（客户ip） | Xielei\Swoole\Protocol::CLIENT_INFO_REMOTE_PORT（客户端口） | Xielei\Swoole\Protocol::CLIENT_INFO_SYSTEM（客户系统信息）
     * @return array|null
     */
    public function getClientInfo(string $client, int $type = 255): ?array
    {
        $address = $this->clientToAddress($client);
        return GetClientInfo::result($this->sendToAddressAndRecv($address, GetClientInfo::encode($address['fd'], $type)));
    }

    /**
     * 获取指定分组下客户绑定的uid列表
     *
     * @param string $group 分组名称
     * @param boolean $unique 是否过滤重复值 默认过滤，若用户数过多，会占用较大内存，建议根据需要设置
     * @return iterable
     */
    public function getUidListByGroup(string $group, bool $unique = true): iterable
    {
        $uid_list = [];
        $buffer = GetUidListByGroup::encode($group);
        foreach ($this->getAddressList() as $address) {
            $res = $this->sendToAddressAndRecv($address, $buffer);
            while ($res) {
                $tmp = unpack('Clen', $res);
                $uid = substr($res, 1, $tmp['len']);
                if ($unique) {
                    if (!isset($uid_list[$uid])) {
                        $uid_list[$uid] = $uid;
                        yield $uid;
                    }
                } else {
                    yield $uid;
                }
                $res = substr($res, 1 + $tmp['len']);
            }
        }
        unset($uid_list);
    }

    /**
     * 获取所有uid列表
     *
     * @param boolean $unique 是否过滤重复值 默认过滤，若用户数过多，会占用较大内存，建议根据需要设置
     * @return iterable
     */
    public function getUidList(bool $unique = true): iterable
    {
        $uid_list = [];
        $buffer = GetUidList::encode();
        foreach ($this->getAddressList() as $address) {
            $res = $this->sendToAddressAndRecv($address, $buffer);
            while ($res) {
                $tmp = unpack('Clen', $res);
                $uid = substr($res, 1, $tmp['len']);
                if ($unique) {
                    if (!isset($uid_list[$uid])) {
                        $uid_list[$uid] = $uid;
                        yield $uid;
                    }
                } else {
                    yield $uid;
                }
                $res = substr($res, 1 + $tmp['len']);
            }
        }
        unset($uid_list);
    }

    /**
     * 获取绑定的uid总数（该数据只是一个近似值），一个uid可能被多个gateway下的客户端绑定，考虑性能原因，并不计算精确值，在不传百分比的情况下，系统会自动计算一个近似百分比，通过该百分比计算出近似的总uid数
     *
     * @param float $unique_percent 唯一百分比，例如：80%。若知道的情况下请尽量填写，这样数据更加准确。
     * @return integer
     */
    public function getUidCount(float $unique_percent = null): int
    {
        $items = [];
        $buffer = GetUidCount::encode($unique_percent ? false : true);
        foreach ($this->getAddressList() as $key => $address) {
            $items[$key] = [
                'address' => $address,
                'buffer' => $buffer,
            ];
        }
        $buffers = $this->sendToAddressListAndRecv($items);

        $count = 0;
        $uid_list = [];
        foreach ($buffers as $key => $buffer) {
            if ($buffer) {
                $data = unpack('Nnum', $buffer);
                $count += $data['num'];

                $res = substr($buffer, 8);
                while ($res) {
                    $tmp = unpack('Clen', $res);
                    $uid_list[] = substr($res, 1, $tmp['len']);
                    $res = substr($res, 1 + $tmp['len']);
                }
            }
        }

        if ($unique_percent) {
            return intval($count * $unique_percent);
        } else {
            if ($uid_list) {
                return intval($count * count(array_unique($uid_list)) / count($uid_list));
            } else {
                return $count;
            }
        }
    }

    /**
     * 获取分组列表
     *
     * @param boolean $unique 是否去除重复数据，默认去除，若分组数据较多（例如百万级别），会占用很大的内存，若能够在业务上处理，请尽量设置为false
     * @return iterable
     */
    public function getGroupList(bool $unique = true): iterable
    {
        $group_list = [];
        $buffer = GetGroupList::encode();
        foreach ($this->getAddressList() as $key => $address) {
            $res = $this->sendToAddressAndRecv($address, $buffer);
            while ($res) {
                $tmp = unpack('Clen', $res);
                $group = substr($res, 1, $tmp['len']);
                if ($unique) {
                    if (!isset($group_list[$group])) {
                        $group_list[$group] = $group;
                        yield $group;
                    }
                } else {
                    yield $group;
                }
                $res = substr($res, 1 + $tmp['len']);
            }
        }
        unset($group_list);
    }

    /**
     * 获取指定分组下的用户数
     *
     * @param string $group 分组名称
     * @return integer
     */
    public function getUidCountByGroup(string $group): int
    {
        return count(iterator_to_array($this->getUidListByGroup($group)));
    }

    /**
     * 关闭客户端
     *
     * @param string $client 客户端
     * @param boolean $force 是否强制关闭，强制关闭会立即关闭客户端，不会等到待发送数据发送完毕就立即关闭
     * @return void
     */
    public function closeClient(string $client, bool $force = false)
    {
        $address = $this->clientToAddress($client);
        $this->sendToAddress($address, CloseClient::encode($address['fd'], $force));
    }

    /**
     * 绑定uid 一个客户端只能绑定一个uid，多次绑定只以最后一个为准，客户端下线会自动解绑，无需手动解绑
     *
     * @param string $client
     * @param string $uid
     * @return void
     */
    public function bindUid(string $client, string $uid)
    {
        $address = $this->clientToAddress($client);
        $this->sendToAddress($address, BindUid::encode($address['fd'], $uid));
    }

    /**
     * 取消绑定uid
     *
     * @param string $client
     * @return void
     */
    public function unBindUid(string $client)
    {
        $address = $this->clientToAddress($client);
        $this->sendToAddress($address, UnBindUid::encode($address['fd']));
    }

    /**
     * 客户端加入到指定分组 客户断开会自动从加入的分组移除，无需手动处理
     *
     * @param string $client 客户端
     * @param string $group 分组名称
     * @return void
     */
    public function joinGroup(string $client, string $group)
    {
        $address = $this->clientToAddress($client);
        $this->sendToAddress($address, JoinGroup::encode($address['fd'], $group));
    }

    /**
     * 将客户端从指定分组移除
     *
     * @param string $client 客户端
     * @param string $group 指定分组
     * @return void
     */
    public function leaveGroup(string $client, string $group)
    {
        $address = $this->clientToAddress($client);
        $this->sendToAddress($address, LeaveGroup::encode($address['fd'], $group));
    }

    /**
     * 解散分组
     *
     * @param string $group 分组名称
     * @return void
     */
    public function unGroup(string $group)
    {
        $buffer = UnGroup::encode($group);
        foreach ($this->getAddressList() as $address) {
            $this->sendToAddress($address, $buffer);
        }
    }

    /**
     * 设置session 直接替换session
     *
     * @param string $client 客户端
     * @param array $session session数据
     * @return void
     */
    public function setSession(string $client, array $session)
    {
        $address = $this->clientToAddress($client);
        $this->sendToAddress($address, SetSession::encode($address['fd'], $session));
    }

    /**
     * 更新指定客户端session 区别于设置session，设置是直接替换，更新会合并旧数据
     *
     * @param string $client
     * @param array $session
     * @return void
     */
    public function updateSession(string $client, array $session)
    {
        $address = $this->clientToAddress($client);
        $this->sendToAddress($address, UpdateSession::encode($address['fd'], $session));
    }

    /**
     * 删除指定客户端的session
     *
     * @param string $client
     * @return void
     */
    public function deleteSession(string $client)
    {
        $address = $this->clientToAddress($client);
        $this->sendToAddress($address, DeleteSession::encode($address['fd']));
    }

    /**
     * 获取指定客户端session
     *
     * @param string $client
     * @return array|null
     */
    public function getSession(string $client): ?array
    {
        $address = $this->clientToAddress($client);
        $buffer = $this->sendToAddressAndRecv($address, GetSession::encode($address['fd']));
        return unserialize($buffer);
    }

    /**
     * 根据地址生成客户端编号
     *
     * @param array $address
     * @return string
     */
    public function addressToClient(array $address): string
    {
        return bin2hex(pack('NnN', ip2long($address['lan_host']), $address['lan_port'], $address['fd']));
    }

    /**
     * 根据客户端编号获取客户端通信地址
     *
     * @param string $client
     * @return array
     */
    public function clientToAddress(string $client): array
    {
        $res = unpack('Nlan_host/nlan_port/Nfd', hex2bin($client));
        $res['lan_host'] = long2ip($res['lan_host']);
        return $res;
    }

    /**
     * 获取网关地址列表
     *
     * @return array
     */
    public function getAddressList(): array
    {
        static $last_time = 0;
        static $addresses = [];
        $now_time = time();
        if ($now_time - $last_time > 1) {
            $last_time = $now_time;
            $client = stream_socket_client('tcp://' . $this->register_host . ':' . $this->register_port, $errno, $errmsg, 5, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT);
            fwrite($client, Protocol::encode(pack('C', Protocol::WORKER_CONNECT) . $this->register_secret_key));
            stream_set_timeout($client, 5);
            $data = unpack('Ccmd/A*load', Protocol::decode(stream_socket_recvfrom($client, 655350)));
            if ($data['cmd'] !== Protocol::BROADCAST_GATEWAY_LIST) {
                throw new Exception("get gateway address list failure!");
            } else {
                $addresses = [];
                if ($data['load'] && (strlen($data['load']) % 6 === 0)) {
                    foreach (str_split($data['load'], 6) as $value) {
                        $address = unpack('Nlan_host/nlan_port', $value);
                        $address['lan_host'] = long2ip($address['lan_host']);
                        $addresses[$address['lan_host'] . ':' . $address['lan_port']] = $address;
                    }
                }
            }
        }
        return $addresses;
    }

    /**
     * 向多个地址发送数据并批量接收
     *
     * @param array $items
     * @param float $timeout 超时时间 单位秒
     * @return array
     */
    public function sendToAddressListAndRecv(array $items, float $timeout = 1): array
    {
        $res = [];
        foreach ($items as $key => $item) {
            $res[$key] = $this->sendToAddressAndRecv($item['address'], $item['buffer'], $timeout);
        }
        return $res;
    }

    /**
     * 向指定地址发送数据后返回数据
     *
     * @param array $address 指定地址
     * @param string $buffer 发送的数据
     * @param float $timeout 超时时间 单位秒
     * @return string
     */
    public function sendToAddressAndRecv(array $address, string $buffer, float $timeout = 1): string
    {
        $buffer = Protocol::encode($buffer);
        static $clients = [];
        $client_key = $address['lan_host'] . ':' . $address['lan_port'];
        if (!isset($clients[$client_key])) {
            $client = stream_socket_client("tcp://{$client_key}", $errno, $errmsg, $timeout, STREAM_CLIENT_PERSISTENT | STREAM_CLIENT_CONNECT);
            if (!$client) {
                throw new Exception("connect to tcp://{$client_key} failure! errmsg:{$errmsg}");
            }
            $clients[$client_key] = $client;
        }

        if (strlen($buffer) !== stream_socket_sendto($clients[$client_key], $buffer)) {
            throw new Exception("send to tcp://{$client_key} failure!");
        }

        stream_set_blocking($clients[$client_key], true);
        stream_set_timeout($clients[$client_key], 1);
        $recv_buf = '';
        $time_start = microtime(true);
        $pack_len = 0;
        while (true) {
            $buf = stream_socket_recvfrom($clients[$client_key], 655350);
            if ($buf !== '' && $buf !== false) {
                $recv_buf .= $buf;
            } else {
                if (feof($clients[$client_key])) {
                    throw new Exception("connection closed! tcp://$address");
                } elseif (microtime(true) - $time_start > $timeout) {
                    break;
                }
                continue;
            }
            $recv_len = strlen($recv_buf);
            if (!$pack_len && $recv_len >= 4) {
                $pack_len = current(unpack('N', $recv_buf));
            }
            if (($pack_len && $recv_len >= $pack_len) || microtime(true) - $time_start > $timeout) {
                break;
            }
        }
        return Protocol::decode(substr($recv_buf, 0, $pack_len));
    }

    /**
     * 向指定地址发送数据
     *
     * @param array $address 指定地址
     * @param string $buffer 数据
     * @return void
     */
    public function sendToAddress(array $address, string $buffer)
    {
        static $clients = [];
        $client_key = $address['lan_host'] . ':' . $address['lan_port'];
        if (!isset($clients[$client_key])) {
            $client = stream_socket_client("tcp://{$client_key}", $errno, $errmsg, 5, STREAM_CLIENT_PERSISTENT | STREAM_CLIENT_CONNECT);
            if (!$client) {
                throw new Exception("connect to tcp://{$client_key} failure! errmsg:{$errmsg}");
            }
            $clients[$client_key] = $client;
        }
        $buffer = Protocol::encode($buffer);
        stream_socket_sendto($clients[$client_key], $buffer);
    }
}
