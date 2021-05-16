<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Swoole\Coroutine\Server\Connection;

interface CmdInterface
{
    /**
     * 返回命令码 一个字节
     *
     * @return integer
     */
    public static function getCommandCode(): int;

    /**
     * 执行命令
     *
     * @param Gateway $gateway
     * @param Connection $conn
     * @param string $buffer
     * @return bool
     */
    public static function execute(Gateway $gateway, Connection $connection, string $buffer): bool;
}
