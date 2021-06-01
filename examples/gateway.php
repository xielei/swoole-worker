<?php

declare(strict_types=1);

use Xielei\Swoole\Gateway;

require_once __DIR__ . '/vendor/autoload.php';

$gateway = new Gateway('127.0.0.1', 9327, 'this is secret_key..');

$gateway->lan_host = '127.0.0.1';
$gateway->lan_port = 7777;

$gateway->listen('127.0.0.1', 8001);
$gateway->listen('127.0.0.1', 8000, SWOOLE_SOCK_TCP, [
    'open_websocket_protocol' => true,
    'open_websocket_close_frame' => true,
]);

$gateway::$debug_mode = true;

$gateway->start();
