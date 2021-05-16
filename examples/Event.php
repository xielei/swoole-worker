<?php

declare(strict_types=1);

use Swoole\Process\Pool;
use Xielei\Swoole\Api;
use Xielei\Swoole\Event as SwooleEvent;
use Xielei\Swoole\Protocol;

require_once __DIR__ . '/vendor/autoload.php';

class Event extends SwooleEvent
{
    public function onWorkerStart(Pool $pool, int $worker_id)
    {
        echo "event onWorkerStart\n";
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
    }

    public function onMessage(string $client, string $data)
    {
        $session = json_encode($_SESSION);
        echo "event onMessage client:{$client} session:{$session} data:{$data}\n";
        if (trim($data) == 'test uid') {
            $this->testUid($client, '100');
        }
    }

    public function onClose(string $client, array $info)
    {
        echo "event onClose {$client}\n";
    }

    /**
     * Undocumented function
     *
     * @param string $client
     * @param int|float|string|bool $uid
     * @return void
     */
    private function testUid(string $client, string $uid)
    {
        Api::sendToClient($client, "test uid...\n");

        // test binduid
        Api::sendToClient($client, "test bindUid {$uid}\n");
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
        Api::sendToClient($client, "test unBindUid...\n");

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
    }
}
