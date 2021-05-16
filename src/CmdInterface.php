<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Swoole\Coroutine\Server\Connection;

interface CmdInterface
{
    /**
     * get command code
     *
     * @return integer
     */
    public static function getCommandCode(): int;

    /**
     * execute command
     *
     * @param Gateway $gateway
     * @param Connection $conn
     * @param string $buffer
     * @return bool
     */
    public static function execute(Gateway $gateway, Connection $connection, string $buffer): bool;
}
