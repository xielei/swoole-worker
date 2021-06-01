# SwooleWorker 介绍

SwooleWorker是基于swoole4开发的一款分布式长连接开发框架。

[【官方网站】](http://www.github.com/xielei/swoole-worker) [【Github】](http://www.github.com/xielei/swoole-worker)

## 应用场景

1. 推送
2. 物联网
3. IM
4. 其他

## 系统架构

![架构图](https://www.ebcms.com/uploads/2021/04-27/6087c1f10c381.png)

基于经典的gateway worker架构，分布式部署，横向扩容。

系统分为三块服务 Register服务 Gateway服务 Worker服务

register是通讯员，当gateway上线下线的时候，负责通知到worker，woker收到通知后就连接或断开与gateway之间的连接

gateway负责维系客户端连接，当客户端有消息后转发客户端信息给worker，并且将worker执行结果转发给客户端

worker接收gateway转发过来的请求，并执行工作任务

## 安装

只推荐composer方式安装，且确保您环境已经安装了swoole4

```cmd
composer require xielei/swoole-worker
```

## 基本接口

* sendToClient(string $client, string $message)
* sendToUid(string $uid, string $message)
* sendToGroup(string $group, string $message, array $without_client_list = [])
* sendToAll(string $message, array $without_client_list = [])
* isOnline(string $client)
* isUidOnline(string $uid): bool
* getClientListByGroup(string $group, string $prev_client = null): iterable
* getClientCount(): int
* getClientCountByGroup(string $group): int
* getClientList(string $prev_client = null): iterable
* getClientListByUid(string $uid, string $prev_client = null): iterable
* getClientInfo(string $client, int $type = 255): array
* getUidListByGroup(string $group, bool $unique = true): iterable
* getUidList(bool $unique = true): iterable
* getUidCount(float $unique_percent = null): int
* getGroupList(bool $unique = true): iterable
* getUidCountByGroup(string $group): int
* closeClient(string $client, bool $force = false)
* bindUid(string $client, string $uid)
* unBindUid(string $client)
* joinGroup(string $client, string $group)
* leaveGroup(string $client, string $group)
* unGroup(string $group)
* setSession(string $client, array $session)
* updateSession(string $client, array $session)
* deleteSession(string $client)
* getSession(string $client): ?array
* sendToAddressListAndRecv(array $items, float $timeout = 1): array
* sendToAddressAndRecv(array $address, string $buffer, float $timeout = 1): string
* sendToAddress(array $address, string $buffer, $timeout = 1)

以上接口若不满足需求，还支持自定义命令~

## 入门示例

### Register服务

```php
<?php

declare(strict_types=1);

use Xielei\Swoole\Register;

require_once __DIR__ . '/vendor/autoload.php';

$register = new Register('127.0.0.1', 9327, 'this is secret_key..');

$register::$debug_mode = true;

$register->start();
```

### Gateway服务

```php
<?php

declare(strict_types=1);

use Xielei\Swoole\Gateway;

require_once __DIR__ . '/vendor/autoload.php';

$gateway = new Gateway('127.0.0.1', 9327, 'this is secret_key..');

$gateway->lan_host = '127.0.0.1';
$gateway->lan_port = 7777;

$gateway->listen('127.0.0.1', 8001);
$gateway->listen('127.0.0.1', 8000, SWOOLE_SOCK_TCP, [
    'open_websocket_protocol' => true,
    'open_websocket_close_frame' => true,
]);

$gateway::$debug_mode = true;

$gateway->start();
```

### Worker服务

```php
<?php

declare(strict_types=1);

use Xielei\Swoole\Worker;

require_once __DIR__ . '/vendor/autoload.php';

$worker = new Worker('127.0.0.1', 9327, 'this is secret_key..');

$worker::$debug_mode = true;

$worker->worker_file = __DIR__ . '/WorkerEvent.php';
$worker->task_file = __DIR__ . '/TaskEvent.php';

$worker->start();
```

### 工作文件

我们的主要开发文件就两个：工作进程和任务进程

1. 工作进程

工作进程是主要的业务逻辑

> 类名必须为 WorkerEvent

```php
<?php

declare (strict_types = 1);

use Swoole\Timer;
use Xielei\Swoole\Api;
use Xielei\Swoole\Helper\WorkerEvent as HelperWorkerEvent;
use Xielei\Swoole\Protocol;

require_once __DIR__ . '/vendor/autoload.php';

class WorkerEvent extends HelperWorkerEvent
{
    public function onWorkerStart()
    {}

    public function onWorkerExit()
    {}

    public function onWorkerStop()
    {}

    public function onFinish(int $task_id, $data)
    {}

    public function onPipeMessage(int $src_worker_id, $message)
    {}

    public function onConnect(string $client)
    {}

    public function onWebsocketConnect(string $client, array $global)
    {}

    public function onMessage(string $client, string $data)
    {}

    public function onClose(string $client, array $bind)
    {}
}
```

2. 任务进程

可以没有任务进程，但是若有耗时或其他操作，可以从工作进程投递到任务进程处理，处理完毕后通知到工作进程

> 类名必须为 TaskEvent

```php
<?php

declare (strict_types = 1);

use Swoole\Timer;
use Xielei\Swoole\Api;
use Xielei\Swoole\Helper\TaskEvent as HelperTaskEvent;
use Xielei\Swoole\Protocol;

require_once __DIR__ . '/vendor/autoload.php';

class TaskEvent extends HelperTaskEvent
{
    public function onWorkerStart()
    {}

    public function onWorkerExit()
    {}

    public function onWorkerStop()
    {}

    public function onTask(int $task_id, int $src_worker_id, $data)
    {}

    public function onPipeMessage(int $src_worker_id, $message)
    {}
}
```

[【开发文档】](https://www.ebcms.com/plugin/manual/home/manual?id=swooles-worker) [【Github】](http://www.github.com/xielei/swoole-worker)
