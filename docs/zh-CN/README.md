# 介绍

SwooleWorker是基于swoole4开发的一款分布式长连接开发框架。常驻内存，协程，高性能高并发；分布式部署，横向扩容，使得能支持庞大的连接数；无感知安全重启，无缝升级代码；接口丰富，支持单个发送，分组发送，群发广播等接口。可广泛应用于云计算、物联网（IOT）、车联网、智能家居、网络游戏等领域。

[【官方网站】](http://swoole.plus)
[【Github】](http://www.github.com/xielei/swoole-worker)

``` bash
  _____                    _   __          __        _
 / ____|                  | |  \ \        / /       | |           ®
| (_____      _____   ___ | | __\ \  /\  / /__  _ __| | _____ _ __
 \___ \ \ /\ / / _ \ / _ \| |/ _ \ \/  \/ / _ \| '__| |/ / _ \ '__|
 ____) \ V  V / (_) | (_) | |  __/\  /\  / (_) | |  |   <  __/ |
|_____/ \_/\_/ \___/ \___/|_|\___| \/  \/ \___/|_|  |_|\_\___|_|

=================================================
SwooleWorker is a distributed long connection
development framework based on Swoole.

[HomePage] https://swoole.plus
=================================================

Press [Ctrl+C] to exit, send 'help' to show help.
> help
****************************  HELP  ****************************
* cmd                           description...
* help                          display help
* exit                          exit cmd panel
* clear                         clear screen
* start [-d]                    start the service,'-d' daemonize mode
* restart [-d]                  restart the service,'-d' daemonize mode
* reload                        reload worker and task
* stop [-f]                     stop the service,'-f' force stop
* status                        displays the running status of the service
****************************************************************
> 
```

## 系统架构

![架构图](https://static.ebcms.com/img/sw.png)

## 安装

``` bash
composer require xielei/swoole-worker
```

## 接口

| 接口                     | 参数                                                            | 返回值   |
| ------------------------ | --------------------------------------------------------------- | -------- |
| sendToClient             | string $client, string $message                                 |          |
| sendToUid                | string $uid, string $message, array $without_client_list = []   |          |
| sendToGroup              | string $group, string $message, array $without_client_list = [] |          |
| sendToAll                | string $message, array $without_client_list = []                |          |
| isOnline                 | string $client                                                  |          |
| isUidOnline              | string $uid                                                     | bool     |
| getClientListByGroup     | string $group, string $prev_client = null                       | iterable |
| getClientCount           |                                                                 | int      |
| getClientCountByGroup    | string $group                                                   | int      |
| getClientList            | string $prev_client = null                                      | iterable |
| getClientListByUid       | string $uid, string $prev_client = null                         | iterable |
| getClientInfo            | string $client, int $type = 255                                 | array    |
| getUidListByGroup        | string $group, bool $unique = true                              | iterable |
| getUidList               | bool $unique = true                                             | iterable |
| getUidCount              | float $unique_percent = null                                    | int      |
| getGroupList             | bool $unique = true                                             | iterable |
| getUidCountByGroup       | string $group                                                   | int      |
| closeClient              | string $client, bool $force = false                             |          |
| bindUid                  | string $client, string $uid                                     |          |
| unBindUid                | string $client                                                  |          |
| joinGroup                | string $client, string $group                                   |          |
| leaveGroup               | string $client, string $group                                   |          |
| unGroup                  | string $group                                                   |          |
| setSession               | string $client, array $session                                  |          |
| updateSession            | string $client, array $session                                  |          |
| deleteSession            | string $client                                                  |          |
| getSession               | string $client                                                  | ?array   |
