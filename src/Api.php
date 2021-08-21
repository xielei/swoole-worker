<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Swoole\Coroutine;
use Swoole\Coroutine\Barrier;
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
use Xielei\Swoole\Library\ClientPool;

class Api
{
    public static $address_list = null;

    /**
     * 给客户端发消息
     *
     * @param string $client 客户端
     * @param string $message 消息内容
     * @return void
     */
    public static function sendToClient(string $client, string $message)
    {
        $address = self::clientToAddress($client);
        self::sendToAddress($address, SendToClient::encode($address['fd'], $message));
    }

    /**
     * 给绑定了指定uid的客户端发消息
     *
     * @param string $uid uid
     * @param string $message 消息内容
     * @param array $without_client_list 要排除的客户端列表
     * @return void
     */
    public static function sendToUid(string $uid, string $message, array $without_client_list = [])
    {
        foreach (self::getClientListByUid($uid) as $client) {
            if (!in_array($client, $without_client_list)) {
                self::sendToClient($client, $message);
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
    public static function sendToGroup(string $group, string $message, array $without_client_list = [])
    {
        foreach (self::$address_list as $address) {
            self::sendToAddress($address, SendToGroup::encode($group, $message, (function () use ($address, $without_client_list): array {
                $res = [];
                foreach ($without_client_list as $client) {
                    $tmp = self::clientToAddress($client);
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
    public static function sendToAll(string $message, array $without_client_list = [])
    {
        foreach (self::$address_list as $address) {
            self::sendToAddress($address, SendToAll::encode($message, (function () use ($address, $without_client_list): array {
                $res = [];
                foreach ($without_client_list as $client) {
                    $tmp = self::clientToAddress($client);
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
    public static function isOnline(string $client): bool
    {
        $address = self::clientToAddress($client);
        return isOnline::result(self::sendToAddressAndRecv($address, IsOnline::encode($address['fd'])));
    }

    /**
     * 判断指定uid是否在线
     *
     * @param string $uid
     * @return boolean
     */
    public static function isUidOnline(string $uid): bool
    {
        foreach (self::getClientListByUid($uid) as $value) {
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
    public static function getClientListByGroup(string $group, string $prev_client = null): iterable
    {
        $start = $prev_client ? false : true;
        if (!$start) {
            $tmp = self::clientToAddress($prev_client);
            $prev_address = [
                'lan_host' => $tmp['lan_host'],
                'lan_port' => $tmp['lan_port'],
            ];
            $prev_fd = $tmp['fd'];
        }
        $buffer = GetClientListByGroup::encode($group);
        foreach (self::$address_list as $address) {
            if ($start || $address == $prev_address) {
                $res = self::sendToAddressAndRecv($address, $buffer);
                foreach (unpack('N*', $res) as $fd) {
                    if ($start || $fd > $prev_fd) {
                        $start = true;
                        yield self::addressToClient([
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
    public static function getClientCount(): int
    {
        $items = [];
        $buffer = GetClientCount::encode();
        foreach (self::$address_list as $key => $address) {
            $items[$key] = [
                'address' => $address,
                'buffer' => $buffer,
            ];
        }
        $buffers = self::sendToAddressListAndRecv($items);

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
    public static function getClientCountByGroup(string $group): int
    {
        $items = [];
        $buffer = GetClientCountByGroup::encode($group);
        foreach (self::$address_list as $key => $address) {
            $items[$key] = [
                'address' => $address,
                'buffer' => $buffer,
            ];
        }
        $buffers = self::sendToAddressListAndRecv($items);

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
    public static function getClientList(string $prev_client = null): iterable
    {
        $start = $prev_client ? false : true;
        if (!$start) {
            $tmp = self::clientToAddress($prev_client);
            $prev_address = [
                'lan_host' => $tmp['lan_host'],
                'lan_port' => $tmp['lan_port'],
            ];
            $prev_fd = $tmp['fd'];
        }
        foreach (self::$address_list as $address) {
            if ($start || $address == $prev_address) {
                $tmp_prev_fd = 0;
                while ($fd_list = unpack('N*', self::sendToAddressAndRecv($address, GetClientList::encode(100, $tmp_prev_fd)))) {
                    foreach ($fd_list as $fd) {
                        if ($start || $fd > $prev_fd) {
                            $start = true;
                            yield self::addressToClient([
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
    public static function getClientListByUid(string $uid, string $prev_client = null): iterable
    {
        $start = $prev_client ? false : true;
        if (!$start) {
            $tmp = self::clientToAddress($prev_client);
            $prev_address = [
                'lan_host' => $tmp['lan_host'],
                'lan_port' => $tmp['lan_port'],
            ];
            $prev_fd = $tmp['fd'];
        }
        $buffer = GetClientListByUid::encode($uid);
        foreach (self::$address_list as $address) {
            if ($start || $address == $prev_address) {
                $res = self::sendToAddressAndRecv($address, $buffer);
                foreach (unpack('N*', $res) as $fd) {
                    if ($start || $fd > $prev_fd) {
                        $start = true;
                        yield self::addressToClient([
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
    public static function getClientInfo(string $client, int $type = 255): ?array
    {
        $address = self::clientToAddress($client);
        return GetClientInfo::result(self::sendToAddressAndRecv($address, GetClientInfo::encode($address['fd'], $type)));
    }

    /**
     * 获取指定分组下客户绑定的uid列表
     *
     * @param string $group 分组名称
     * @param boolean $unique 是否过滤重复值 默认过滤，若用户数过多，会占用较大内存，建议根据需要设置
     * @return iterable
     */
    public static function getUidListByGroup(string $group, bool $unique = true): iterable
    {
        $uid_list = [];
        $buffer = GetUidListByGroup::encode($group);
        foreach (self::$address_list as $address) {
            $res = self::sendToAddressAndRecv($address, $buffer);
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
    public static function getUidList(bool $unique = true): iterable
    {
        $uid_list = [];
        $buffer = GetUidList::encode();
        foreach (self::$address_list as $address) {
            $res = self::sendToAddressAndRecv($address, $buffer);
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
    public static function getUidCount(float $unique_percent = null): int
    {
        $items = [];
        $buffer = GetUidCount::encode($unique_percent ? false : true);
        foreach (self::$address_list as $key => $address) {
            $items[$key] = [
                'address' => $address,
                'buffer' => $buffer,
            ];
        }
        $buffers = self::sendToAddressListAndRecv($items);

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
    public static function getGroupList(bool $unique = true): iterable
    {
        $group_list = [];
        $buffer = GetGroupList::encode();
        foreach (self::$address_list as $key => $address) {
            $res = self::sendToAddressAndRecv($address, $buffer);
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
    public static function getUidCountByGroup(string $group): int
    {
        return count(iterator_to_array(self::getUidListByGroup($group)));
    }

    /**
     * 关闭客户端
     *
     * @param string $client 客户端
     * @param boolean $force 是否强制关闭，强制关闭会立即关闭客户端，不会等到待发送数据发送完毕就立即关闭
     * @return void
     */
    public static function closeClient(string $client, bool $force = false)
    {
        $address = self::clientToAddress($client);
        self::sendToAddress($address, CloseClient::encode($address['fd'], $force));
    }

    /**
     * 绑定uid 一个客户端只能绑定一个uid，多次绑定只以最后一个为准，客户端下线会自动解绑，无需手动解绑
     *
     * @param string $client
     * @param string $uid
     * @return void
     */
    public static function bindUid(string $client, string $uid)
    {
        $address = self::clientToAddress($client);
        self::sendToAddress($address, BindUid::encode($address['fd'], $uid));
    }

    /**
     * 取消绑定uid
     *
     * @param string $client
     * @return void
     */
    public static function unBindUid(string $client)
    {
        $address = self::clientToAddress($client);
        self::sendToAddress($address, UnBindUid::encode($address['fd']));
    }

    /**
     * 客户端加入到指定分组 客户断开会自动从加入的分组移除，无需手动处理
     *
     * @param string $client 客户端
     * @param string $group 分组名称
     * @return void
     */
    public static function joinGroup(string $client, string $group)
    {
        $address = self::clientToAddress($client);
        self::sendToAddress($address, JoinGroup::encode($address['fd'], $group));
    }

    /**
     * 将客户端从指定分组移除
     *
     * @param string $client 客户端
     * @param string $group 指定分组
     * @return void
     */
    public static function leaveGroup(string $client, string $group)
    {
        $address = self::clientToAddress($client);
        self::sendToAddress($address, LeaveGroup::encode($address['fd'], $group));
    }

    /**
     * 解散分组
     *
     * @param string $group 分组名称
     * @return void
     */
    public static function unGroup(string $group)
    {
        $buffer = UnGroup::encode($group);
        foreach (self::$address_list as $address) {
            self::sendToAddress($address, $buffer);
        }
    }

    /**
     * 设置session 直接替换session
     *
     * @param string $client 客户端
     * @param array $session session数据
     * @return void
     */
    public static function setSession(string $client, array $session)
    {
        $address = self::clientToAddress($client);
        self::sendToAddress($address, SetSession::encode($address['fd'], $session));
    }

    /**
     * 更新指定客户端session 区别于设置session，设置是直接替换，更新会合并旧数据
     *
     * @param string $client
     * @param array $session
     * @return void
     */
    public static function updateSession(string $client, array $session)
    {
        $address = self::clientToAddress($client);
        self::sendToAddress($address, UpdateSession::encode($address['fd'], $session));
    }

    /**
     * 删除指定客户端的session
     *
     * @param string $client
     * @return void
     */
    public static function deleteSession(string $client)
    {
        $address = self::clientToAddress($client);
        self::sendToAddress($address, DeleteSession::encode($address['fd']));
    }

    /**
     * 获取指定客户端session
     *
     * @param string $client
     * @return array|null
     */
    public static function getSession(string $client): ?array
    {
        $address = self::clientToAddress($client);
        $buffer = self::sendToAddressAndRecv($address, GetSession::encode($address['fd']));
        return unserialize($buffer);
    }

    // 以下为核心基本方法

    /**
     * 向多个地址发送数据并批量接收
     *
     * @param array $items
     * @param float $timeout 超时时间 单位秒
     * @return array
     */
    public static function sendToAddressListAndRecv(array $items, float $timeout = 1): array
    {
        $barrier = Barrier::make();
        $res = [];
        foreach ($items as $key => $item) {
            Coroutine::create(function () use ($barrier, $key, $item, $timeout, &$res) {
                $res[$key] = self::sendToAddressAndRecv($item['address'], $item['buffer'], $timeout);
            });
        }
        Barrier::wait($barrier);
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
    public static function sendToAddressAndRecv(array $address, string $buffer, float $timeout = 1): string
    {
        return Protocol::decode(self::getConnPool($address['lan_host'], $address['lan_port'])->sendAndRecv(Protocol::encode($buffer), $timeout));
    }

    /**
     * 向指定地址发送数据
     *
     * @param array $address 指定地址
     * @param string $buffer 数据
     * @return void
     */
    public static function sendToAddress(array $address, string $buffer)
    {
        self::getConnPool($address['lan_host'], $address['lan_port'])->send(Protocol::encode($buffer));
    }

    public static function getConnPool($host, $port, int $size = 64): ClientPool
    {
        static $pools = [];
        if (!isset($pools[$host . ':' . $port])) {
            $pools[$host . ':' . $port] = new ClientPool($host, $port, $size);
        }
        return $pools[$host . ':' . $port];
    }

    public static function addressToClient(array $address): string
    {
        return bin2hex(pack('NnN', ip2long($address['lan_host']), $address['lan_port'], $address['fd']));
    }

    public static function clientToAddress(string $client): array
    {
        $res = unpack('Nlan_host/nlan_port/Nfd', hex2bin($client));
        $res['lan_host'] = long2ip($res['lan_host']);
        return $res;
    }
}
