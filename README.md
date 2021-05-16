# SwooleWorker

SwooleWorker is a distributed long connection development framework based on Swoole4.

[【Github】](http://www.github.com/xielei/swoole-worker)
[【HomePage】](http://www.github.com/xielei/swoole-worker)

## Manual

[【ENGLISH】](docs/en)
[【简体中文】](docs/zh-CN)

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

## System structure

![structure](https://www.ebcms.com/uploads/2021/04-27/6087c1f10c381.png)

## Api preview

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

If the above interfaces do not meet the requirements, custom commands are also supported~
