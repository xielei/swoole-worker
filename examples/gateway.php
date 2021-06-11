<?php

declare(strict_types=1);

use Xielei\Swoole\Gateway;

require_once __DIR__ . '/../vendor/autoload.php';

$gateway = new Gateway();

$gateway->register_host = '127.0.0.1';
$gateway->register_port = 9327;
$gateway->register_secret_key = 'this is secret_key..';

$gateway->lan_host = '127.0.0.1';
$gateway->lan_port = 9108;

$gateway->router = function (int $fd, int $cmd) use ($gateway) {
    if ($gateway->worker_pool_list) {
        return $gateway->worker_pool_list[array_keys($gateway->worker_pool_list)[$fd % count($gateway->worker_pool_list)]];
    }
};

$gateway->listen('127.0.0.1', 8000);
$gateway->listen('127.0.0.1', 8001, [
    'open_websocket_protocol' => true,
    'open_websocket_close_frame' => true,
]);

$gateway->start();
