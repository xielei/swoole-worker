<?php

declare(strict_types=1);

use Swoole\Timer;
use Xielei\Swoole\Api;
use Xielei\Swoole\Helper\WorkerEvent as HelperWorkerEvent;
use Xielei\Swoole\Protocol;

class WorkerEvent extends HelperWorkerEvent
{
    public function onWorkerStart()
    {
        echo "event onWorkerStart\n";
    }

    public function onWorkerStop()
    {
        echo "event onWorkerStop\n";
    }

    public function onConnect(string $client, array $session)
    {
        echo "event onConnect {$client}\n";
    }

    public function onReceive(string $client, array $session, string $data)
    {
        echo "event onMessage client:{$client} session:{$session} data:{$data}\n";

        for ($i = 0; $i < 10; $i++) {
            Api::sendToClient($client, "\033[2J");
            Api::sendToClient($client, "\033[0;0H");
            $this->testUid($client);
            $this->testGroup($client);
            $this->testSession($client);
        }
    }

    public function onWorkerExit()
    {
        if ($this->timer) {
            Timer::clear($this->timer);
            unset($this->timer);
        }
    }

    public function onClose(string $client, array $session, array $bind)
    {
        $bind = json_encode($bind);
        echo "event onClose {$client} info:{$bind}\n";
    }

    private function testUid(string $client)
    {
        $uid = '11';

        Api::sendToClient($client, "test uid start...\n");

        // test binduid
        Api::bindUid($client, $uid);
        $info = Api::getClientInfo($client, Protocol::CLIENT_INFO_UID);
        if ($info['uid'] === $uid) {
            Api::sendToClient($client, "test bindUid success\n");
        } else {
            $info = json_encode($info);
            Api::sendToClient($client, "test bindUid failure info:{$info}\n");
        }

        $uid_list = iterator_to_array(Api::getUidList(true));
        if ($uid_list === [$uid]) {
            Api::sendToClient($client, "test getUidList success\n");
        } else {
            $uid_list = json_encode($uid_list);
            Api::sendToClient($client, "test getUidList failure uid_list:{$uid_list}\n");
        }

        if (Api::isUidOnline($uid) === true) {
            Api::sendToClient($client, "test isUidOnline success\n");
        } else {
            Api::sendToClient($client, "test isUidOnline failure\n");
        }

        $client_list = iterator_to_array(Api::getClientListByUid($uid));
        if ($client_list === [$client]) {
            Api::sendToClient($client, "test getClientListByUid success\n");
        } else {
            Api::sendToClient($client, "test getClientListByUid failure\n");
        }

        if (Api::getUidCount() === 1) {
            Api::sendToClient($client, "test getUidCount success\n");
        } else {
            Api::sendToClient($client, "test getUidCount failure\n");
        }

        Api::unBindUid($client);
        $info = Api::getClientInfo($client, Protocol::CLIENT_INFO_UID);
        if (!$info['uid']) {
            Api::sendToClient($client, "test unBindUid success\n");
        } else {
            $info = json_encode($info);
            Api::sendToClient($client, "test unBindUid failure info:{$info}\n");
        }

        $uid_list = iterator_to_array(Api::getUidList(true));
        if ($uid_list === []) {
            Api::sendToClient($client, "test getUidList success\n");
        } else {
            $uid_list = json_encode($uid_list);
            Api::sendToClient($client, "test getUidList failure uid_list:{$uid_list}\n");
        }

        if (false === Api::isUidOnline($uid)) {
            Api::sendToClient($client, "test isUidOnline success\n");
        } else {
            Api::sendToClient($client, "test isUidOnline failure\n");
        }

        $client_list = iterator_to_array(Api::getClientListByUid($uid));
        if ($client_list === []) {
            Api::sendToClient($client, "test getClientListByUid success\n");
        } else {
            Api::sendToClient($client, "test getClientListByUid failure\n");
        }

        if (Api::getUidCount() === 0) {
            Api::sendToClient($client, "test getUidCount success\n");
        } else {
            Api::sendToClient($client, "test getUidCount failure\n");
        }

        Api::sendToClient($client, "test uid end\n");
    }

    private function testGroup(string $client)
    {
        Api::sendToClient($client, "test group start...\n");

        $group1 = 'testgroup1';
        $group2 = 'testgroup2';
        Api::unGroup($group1);
        Api::unGroup($group2);

        // test joinGroup
        Api::joinGroup($client, $group1);
        $info = Api::getClientInfo($client, Protocol::CLIENT_INFO_GROUP_LIST);
        if ($info['group_list'] === [$group1]) {
            Api::sendToClient($client, "test joinGroup success\n");
        } else {
            $info = json_encode($info);
            Api::sendToClient($client, "test joinGroup failure info:{$info}\n");
        }

        $group_list = iterator_to_array(Api::getGroupList(true));
        if ($group_list === [$group1]) {
            Api::sendToClient($client, "test getGroupList success\n");
        } else {
            $group_list = json_encode($group_list);
            Api::sendToClient($client, "test getGroupList failure group_list:{$group_list}\n");
        }

        $client_list = iterator_to_array(Api::getClientListByGroup($group1));
        if ($client_list === [$client]) {
            Api::sendToClient($client, "test getClientListByGroup success\n");
        } else {
            Api::sendToClient($client, "test getClientListByGroup failure\n");
        }

        if (Api::getClientCountByGroup($group1) === 1) {
            Api::sendToClient($client, "test getClientCountByGroup success\n");
        } else {
            Api::sendToClient($client, "test getClientCountByGroup failure\n");
        }

        if (Api::getUidCountByGroup($group1) === 0) {
            Api::sendToClient($client, "test getUidCountByGroup success\n");
        } else {
            Api::sendToClient($client, "test getUidCountByGroup failure\n");
        }

        $uid = '100';
        Api::bindUid($client, $uid);

        $uid_list = iterator_to_array(Api::getUidListByGroup($group1));
        if ($uid_list === [$uid]) {
            Api::sendToClient($client, "test getUidListByGroup success\n");
        } else {
            Api::sendToClient($client, "test getUidListByGroup failure\n");
        }

        if (Api::getUidCountByGroup($group1) === 1) {
            Api::sendToClient($client, "test getUidCountByGroup success\n");
        } else {
            Api::sendToClient($client, "test getUidCountByGroup failure\n");
        }

        Api::joinGroup($client, $group2);

        $info = Api::getClientInfo($client, Protocol::CLIENT_INFO_GROUP_LIST);
        if ($info['group_list'] === [$group1, $group2]) {
            Api::sendToClient($client, "test joinGroup success\n");
        } else {
            $info = json_encode($info);
            Api::sendToClient($client, "test joinGroup failure info:{$info}\n");
        }

        if (Api::getUidCountByGroup($group2) === 1) {
            Api::sendToClient($client, "test getUidCountByGroup success\n");
        } else {
            Api::sendToClient($client, "test getUidCountByGroup failure\n");
        }

        $group_list = iterator_to_array(Api::getGroupList(true));
        if ($group_list === [$group1, $group2]) {
            Api::sendToClient($client, "test getGroupList success\n");
        } else {
            $group_list = json_encode($group_list);
            Api::sendToClient($client, "test getGroupList failure group_list:{$group_list}\n");
        }

        $client_list = iterator_to_array(Api::getClientListByGroup($group2));
        if ($client_list === [$client]) {
            Api::sendToClient($client, "test getClientListByGroup success\n");
        } else {
            Api::sendToClient($client, "test getClientListByGroup failure\n");
        }

        if (Api::getClientCountByGroup($group2) === 1) {
            Api::sendToClient($client, "test getClientCountByGroup success\n");
        } else {
            Api::sendToClient($client, "test getClientCountByGroup failure\n");
        }

        $uid_list = iterator_to_array(Api::getUidListByGroup($group2));
        if ($uid_list === [$uid]) {
            Api::sendToClient($client, "test getUidListByGroup success\n");
        } else {
            Api::sendToClient($client, "test getUidListByGroup failure\n");
        }

        Api::leaveGroup($client, $group2);

        if (Api::getUidCountByGroup($group1) === 1) {
            Api::sendToClient($client, "test getUidCountByGroup success\n");
        } else {
            Api::sendToClient($client, "test getUidCountByGroup failure\n");
        }

        if (Api::getUidCountByGroup($group2) === 0) {
            Api::sendToClient($client, "test getUidCountByGroup success\n");
        } else {
            Api::sendToClient($client, "test getUidCountByGroup failure\n");
        }

        $info = Api::getClientInfo($client, Protocol::CLIENT_INFO_GROUP_LIST);
        if ($info['group_list'] === [$group1]) {
            Api::sendToClient($client, "test getClientInfo success\n");
        } else {
            $info = json_encode($info);
            Api::sendToClient($client, "test getClientInfo failure info:{$info}\n");
        }

        $group_list = iterator_to_array(Api::getGroupList(true));
        if ($group_list === [$group1]) {
            Api::sendToClient($client, "test getGroupList success\n");
        } else {
            $group_list = json_encode($group_list);
            Api::sendToClient($client, "test getGroupList failure group_list:{$group_list} [{$group1}]\n");
        }

        $client_list = iterator_to_array(Api::getClientListByGroup($group1));
        if ($client_list === [$client]) {
            Api::sendToClient($client, "test getClientListByGroup success\n");
        } else {
            $client_list = json_encode($client_list);
            Api::sendToClient($client, "test getClientListByGroup failure {$client_list} === [{$client}]\n");
        }

        if (Api::getClientCountByGroup($group1) === 1) {
            Api::sendToClient($client, "test getClientCountByGroup success\n");
        } else {
            Api::sendToClient($client, "test getClientCountByGroup failure\n");
        }

        $uid_list = iterator_to_array(Api::getUidListByGroup($group1));
        if ($uid_list === [$uid]) {
            Api::sendToClient($client, "test getUidListByGroup success\n");
        } else {
            $uid_list = json_encode($uid_list);
            Api::sendToClient($client, "test getUidListByGroup failure {$uid_list} === [{$uid}]\n");
        }

        // Api::leaveGroup($client, $group1);
        Api::leaveGroup($client, $group2);

        Api::sendToClient($client, "test group end\n");
    }

    private function testSession($client)
    {
        Api::sendToClient($client, "test session start...\n");

        $address = unpack('Nlan_host/nlan_port/Nfd', hex2bin($client));
        $address['lan_host'] = long2ip($address['lan_host']);
        $address['fd'] += 1000;
        $client2 = bin2hex(pack('NnN', ip2long($address['lan_host']), $address['lan_port'], $address['fd']));
        if (Api::getSession($client2) === null) {
            Api::sendToClient($client, "test getSession success\n");
        } else {
            Api::sendToClient($client, "test getSession failure\n");
        }

        Api::deleteSession($client);

        if (Api::getSession($client) === []) {
            Api::sendToClient($client, "test getSession success\n");
        } else {
            Api::sendToClient($client, "test getSession failure\n");
        }

        $session = ['a' => 'b', 'd' => 2];
        Api::setSession($client, $session);

        if (Api::getSession($client) === $session) {
            Api::sendToClient($client, "test setSession success\n");
        } else {
            Api::sendToClient($client, "test setSession failure\n");
        }

        Api::updateSession($client, ['a' => 2, 'b' => 3]);

        if (Api::getSession($client) === ['a' => 2, 'd' => 2, 'b' => 3]) {
            Api::sendToClient($client, "test updateSession success\n");
        } else {
            $tmp = json_encode(Api::getSession($client));
            Api::sendToClient($client, "test updateSession failure {$tmp}\n");
        }

        Api::setSession($client, ['sd' => 22]);
        if (Api::getSession($client) === ['sd' => 22]) {
            Api::sendToClient($client, "test setSession success\n");
        } else {
            Api::sendToClient($client, "test setSession failure\n");
        }

        Api::deleteSession($client);

        if (Api::getSession($client) === []) {
            Api::sendToClient($client, "test deleteSession success\n");
        } else {
            Api::sendToClient($client, "test deleteSession failure\n");
        }

        Api::sendToClient($client, "test session end\n");
    }
}
