<?php

declare(strict_types=1);

namespace Xielei\Swoole\Interfaces;

use Swoole\Coroutine\Server\Connection;
use Xielei\Swoole\Gateway;

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
     * @param Connection $connection
     * @param string $buffer
     * @return void
     */
    public static function execute(Gateway $gateway, Connection $connection, string $buffer);
}
