# 介绍

**SwooleWorker**是基于swoole4开发的一款分布式长连接开发框架。常驻内存，协程，高性能高并发；分布式部署，横向扩容，使得能支持庞大的连接数；无感知安全重启，无缝升级代码；接口丰富，支持单个发送，分组发送，群发广播等接口。可广泛应用于云计算、物联网（IOT）、车联网、智能家居、网络游戏等领域。

``` bash
  _____                    _   __          __        _
 / ____|                  | |  \ \        / /       | |           ®
| (_____      _____   ___ | | __\ \  /\  / /__  _ __| | _____ _ __
 \___ \ \ /\ / / _ \ / _ \| |/ _ \ \/  \/ / _ \| '__| |/ / _ \ '__|
 ____) \ V  V / (_) | (_) | |  __/\  /\  / (_) | |  |   <  __/ |
|_____/ \_/\_/ \___/ \___/|_|\___| \/  \/ \___/|_|  |_|\_\___|_|

=================================================
SwooleWorker is a distributed long connection
development framework based on Swoole4.

[Github] https://github.com/xielei/swoole-worker
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

[【Github】](http://www.github.com/xielei/swoole-worker) [【官方网站】](http://www.github.com/xielei/swoole-worker)

## 应用场景

1. 物联网
2. 云计算
3. 车联网
4. 智能家居
5. 网络游戏
6. 其他

## 安装

推荐composer方式安装，且确保您环境已经安装了swoole4

``` cmd
composer require xielei/swoole-worker
```

## 启动

系统有三类服务：

1. register 注册中心
2. gateway 网关服务
3. worker 工作服务

系统必须cli命令行启动：

``` bash
php your_register.php
```

``` bash
php your_gateway.php
```

``` bash
php your_worker.php
```

启动后进入命令行交互界面，发送`help`可查看命令帮助
