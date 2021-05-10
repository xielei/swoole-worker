# SwooleWorker 介绍

SwooleWorker是基于swoole4开发的一款分布式长连接开发框架。

[【开发文档】](https://www.ebcms.com/plugin/manual/home/manual?id=swooles-worker) [【Github】](http://www.github.com/xielei/swoole-worker)

## 应用场景

1. 推送
2. 物联网
3. IM
4. 其他

## 系统架构

![架构图](https://www.ebcms.com/uploads/2021/04-27/6087c1f10c381.png)

我是一张图片

基于经典的gateway worker架构，分布式部署，横向扩容。

系统分为三块服务 Register服务 Gateway服务 Worker服务

register是通讯员，当gateway上线下线的时候，负责通知到worker，woker收到通知后就连接或断开与gateway之间的连接

gateway负责维系客户端连接，当客户端有消息后转发客户端信息给worker，并且将worker执行结果转发给客户端

worker接收gateway转发过来的请求，并执行工作任务

## 安装

只推荐composer方式安装，且确保您环境已经安装了swoole4

``` cmd
composer require xielei/swoole-worker
```

## 基本接口

* sendToClient
* sendToClients
* sendToAll
* isOnline
* closeClient
* getClientIdCount
* getClientIdList
* 其他

以上接口若不满足需求，还支持自定义命令~

## 入门示例

### Register服务

``` php
<?php

use Xielei\Swoole\Register;

require_once __DIR__ . '/vendor/autoload.php';

$register = new Register('127.0.0.1', 3327);
$register->secret_key = 'this is secret_key..';

$register->start();
```

### Gateway服务

```php
<?php

use Xielei\Swoole\Gateway;

require_once __DIR__ . '/vendor/autoload.php';

// 客户端连接地址
$gateway = new Gateway('127.0.0.1', 8000);

// 供内部通讯的地址端口
$gateway->lan_host = '127.0.0.1';
$gateway->lan_port_start = 7777;

// 注册中心 地址端口密钥
$worker->register_host = '127.0.0.1';
$worker->register_port = 3327;
$gateway->register_secret_key = 'this is secret_key..';

$gateway->start();
```

### Worker服务

```php
<?php

use Xielei\Swoole\Worker;

require_once __DIR__ . '/vendor/autoload.php';

include __DIR__ . '/Event.php';

$worker = new Worker(new Event, 2);

// 注册中心 地址端口密钥
$worker->register_host = '127.0.0.1';
$worker->register_port = 3327;
$worker->register_secret_key = 'this is secret_key..';

$worker->start();
```

[【开发文档】](https://www.ebcms.com/plugin/manual/home/manual?id=swooles-worker) [【Github】](http://www.github.com/xielei/swoole-worker)
