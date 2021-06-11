# SwooleWorker

SwooleWorker is a distributed long connection development framework based on swoole4. Home furnishing memory, co channel, distributed deployment, horizontal expansion, no sense of security restart, high performance and high concurrency, SwooleWorker can be widely used in cloud computing, IOT, vehicle networking, smart home, online games, Internet plus, mobile communications and other fields. Using swooleworker can greatly improve the efficiency of enterprise IT R & D team and focus more on the development of innovative products.

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

## Manual

[【ENGLISH】](docs/en/README.md)
[【简体中文】](docs/zh-CN/README.md)

## Usage scenarios

1. Push
2. IOT
3. IM
4. Games
5. Others

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
* getConnPool($host, $port): ClientPool
* addressToClient(array $address): string
* clientToAddress(string $client): array

If the above interfaces do not meet the requirements, custom commands are also supported~

-------------

[【Github】](http://www.github.com/xielei/swoole-worker)
[【HomePage】](http://www.github.com/xielei/swoole-worker)
