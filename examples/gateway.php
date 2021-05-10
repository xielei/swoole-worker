<?php

use Xielei\Swoole\Gateway;

require_once __DIR__ . '/../vendor/autoload.php';

$gateway = new Gateway('127.0.0.1', 8000);

$gateway->lan_host = '127.0.0.1';
$gateway->lan_port_start = 7777;

$gateway->set([
    'worker_num' => 2, // worker process num
    // 'backlog' => 128, // listen backlog
    // 'max_request' => 50,
    // 'dispatch_mode' => 4,
    // 'daemonize' => true,
    // 'open_websocket_protocol' => true,
    // 'open_websocket_close_frame' => true,
]);

$gateway->register_host = '127.0.0.1';
$gateway->register_secret_key = 'this is secret_key..';

$gateway->start();
