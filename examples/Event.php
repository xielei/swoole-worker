<?php

use Swoole\Process\Pool;
use Swoole\Timer;
use Xielei\Swoole\Api;
use Xielei\Swoole\Event as SwooleEvent;
use Xielei\Swoole\Protocol;

require_once __DIR__ . '/../vendor/autoload.php';

class Event extends SwooleEvent
{
    public function onWorkerStart(Pool $pool, int $worker_id)
    {
        echo "event onWorkerStart\n";
        $tid = Timer::tick(1, function () {
            Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
            // Api::getUidCount();
        });
        Timer::after(1000, function () use ($tid) {
            // Timer::clear($tid);
            // echo "关闭xx\n";
        });
        // Timer::tick(1, function () {
        //     Api::getUidCount();
        //     // Api::getUidCount();
        //     // Api::getUidCount();
        //     // Api::getUidCount();
        // });
    }

    public function onWorkerStop(Pool $pool, int $worker_id)
    {
        echo "event onWorkerStop\n";
    }

    public function onWebsocketConnect(string $client, array $global)
    {
        echo "event onWebsocketConnect {$client}\n";
    }

    public function onConnect(string $client)
    {
        echo "event onConnect {$client}\n";
        Api::bindUid($client, 'uid_' . uniqid());
        Api::joinGroup($client, 'mygroup');
        Api::joinGroup($client, 'mygroup2');
        Api::setSession($client, ['TEST' => 'FOO']);
    }

    public function onMessage(string $client, string $data)
    {
        $session = json_encode($_SESSION);
        echo "event onMessage client:{$client} session:{$session} data:{$data}\n";

        $data = trim($data);

        if ($data == 'aa') {
            Api::closeClient($client);
        }

        if ($data == 'bb') {
            print_r(iterator_to_array(Api::getClientListByUid('aa')));
        }

        if ($data == 'll') {
            print_r(iterator_to_array(Api::getClientList()));
        }

        if ($data == 'ss') {
            print_r(Api::getClientInfo($client, Protocol::CLIENT_INFO_SYSTEM | Protocol::CLIENT_INFO_SESSION));
        }

        if ($data == 'sa') {
            print_r(Api::getSession($client));
            print_r(Api::getClientInfo($client));
        }

        if ($data == 'uu') {
            print_r(iterator_to_array(Api::getUidList()));
        }

        if ($data == 'cc') {
            print_r(Api::getUidCount());
        }

        if ($data == 'session') {
            var_dump(Api::getSession($client));
            Api::setSession($client, ['dd' => 99]);
            var_dump(Api::getSession($client));
            Api::updateSession($client, ['SS' => 99]);
            var_dump(Api::getSession($client));
            Api::deleteSession($client);
            var_dump(Api::getSession($client));
        }

        if ($data == 'group3') {
            Api::leaveGroup($client, 'group3');
        }

        if ($data == 'group') {
            var_dump(iterator_to_array(Api::getGroupList()));
            Api::joinGroup($client, 'group1');
            Api::joinGroup($client, 'group2');
            Api::joinGroup($client, 'group3');
            var_dump(iterator_to_array(Api::getGroupList()));
            var_dump(Api::getClientInfo($client, Protocol::CLIENT_INFO_GROUP_LIST));
            Api::leaveGroup($client, 'group1');
            var_dump(Api::getClientInfo($client, Protocol::CLIENT_INFO_GROUP_LIST));
            Api::unGroup('group2');
            var_dump(Api::getClientInfo($client, Protocol::CLIENT_INFO_GROUP_LIST));
            var_dump(iterator_to_array(Api::getGroupList()));
            Api::sendToGroup('group2', "这是发送到group2的消息\n");
            Api::sendToGroup('group3', "这是发送到group3的消息\n");
            var_dump(iterator_to_array(Api::getUidListByGroup('group3')));
            var_dump(iterator_to_array(Api::getUidListByGroup('group2')));
            var_dump(iterator_to_array(Api::getClientListByGroup('group2')));
            var_dump(iterator_to_array(Api::getClientListByGroup('group3')));
        }
    }

    public function onClose(string $client)
    {
        echo "event onClose {$client}\n";
    }
}
