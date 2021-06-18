# SwooleWorker

SwooleWorker is a distributed long connection development framework based on swoole4. Resident memory, coroutine, high performance and high concurrency; Distributed deployment and horizontal expansion can support a large number of connections; No perception security restart, seamless upgrade code; Interface rich, support single send, packet send, group broadcast interface. It can be widely used in cloud computing, Internet of things (IOT), Internet of vehicles, smart home, online games and other fields.

[【ENGLISH】](docs/en/)
[【简体中文】](docs/zh-CN/)

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

## Structure

![架构图](https://static.ebcms.com/img/sw.png)

## Install

``` bash
composer require xielei/swoole-worker
```

## Api

* sendToClient(string $client, string $message)
* sendToUid(string $uid, string $message)
* sendToGroup(string $group, string $message, array $without_client_list = [])
* sendToAll(string $message, array $without_client_list = [])
* isOnline(string $client): bool
* isUidOnline(string $uid): bool
* getClientListByGroup(string $group, string $prev_client = null): iterable
* getClientCount(): int
* getClientCountByGroup(string $group): int
* getClientList(string $prev_client = null): iterable
* getClientListByUid(string $uid, string $prev_client = null): iterable
* getClientInfo(string $client, int $type = 255): ?array
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
* sendToAddress(array $address, string $buffer)
* getConnPool($host, $port, int $size = 64): ClientPool
* addressToClient(array $address): string
* clientToAddress(string $client): array

If the above interfaces do not meet the requirements, custom commands are also supported~

-------------

[【Github】](http://www.github.com/xielei/swoole-worker)
[【HomePage】](http://www.github.com/xielei/swoole-worker)
