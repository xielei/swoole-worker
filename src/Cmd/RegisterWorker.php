<?php

declare(strict_types=1);

namespace Xielei\Swoole\Cmd;

use Swoole\ConnectionPool;
use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Gateway;

class RegisterWorker implements CmdInterface
{
    public static function getCommandCode(): int
    {
        return 19;
    }

    public static function encode(array $tag_list = []): string
    {
        return pack('C', self::getCommandCode()) . json_encode(array_values($tag_list));
    }

    public static function decode(string $buffer): array
    {
        return [
            'tag_list' => json_decode($buffer, true),
        ];
    }

    public static function execute(Gateway $gateway, Connection $conn, string $buffer)
    {
        $res = self::decode($buffer);
        $address = implode(':', $conn->exportSocket()->getpeername());
        if (isset($gateway->worker_list[$address])) {
            $gateway->worker_list[$address]['tag_list'] = $res['tag_list'];
        } else {
            $gateway->worker_list[$address] = [
                'tag_list' => $res['tag_list'],
                'pool' => new ConnectionPool(function () use ($conn) {
                    return $conn;
                }, 1),
            ];
        }
    }
}
