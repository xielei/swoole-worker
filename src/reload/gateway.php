<?php

use Swoole\Server;
use Swoole\Timer;
use Xielei\Swoole\Worker;

/**
 * @var Worker $this
 */

$this->on('Connect', function (Server $server, ...$args) {

    $fd = $args[0]->fd;
    $this->throttle_fd_list[$fd] = [
        'timer' => Timer::tick($this->throttle_interval, function () use ($fd) {
            $this->throttle_fd_list[$fd]['times'] = $this->throttle_times;
        }),
        'times' => $this->throttle_times,
    ];

    $this->sendToProcess([
        'event' => 'Connect',
        'args' => json_decode(json_encode($args), true),
    ]);
});

$this->on('Open', function (Server $server, ...$args) {

    $fd = $args[0]->fd;
    $this->throttle_fd_list[$fd] = [
        'timer' => Timer::tick($this->throttle_interval, function () use ($fd) {
            $this->throttle_fd_list[$fd]['times'] = $this->throttle_times;
        }),
        'times' => $this->throttle_times,
    ];

    $this->sendToProcess([
        'event' => 'Open',
        'args' => json_decode(json_encode($args), true),
    ]);
});

$this->on('Receive', function (Server $server, ...$args) {

    $fd = $args[0]->fd;
    $this->throttle_fd_list[$fd]['times'] -= 1;
    if ($this->throttle_fd_list[$fd]['times'] < 0) {
        if ($this->throttle_close) {
            $server->close($fd, $this->throttle_close == 2 ? true : null);
        }
    } else {
        $this->sendToProcess([
            'event' => 'Receive',
            'args' => json_decode(json_encode($args), true),
        ]);
    }
});

$this->on('Message', function (Server $server, ...$args) {

    $fd = $args[0]->fd;
    $this->throttle_fd_list[$fd]['times'] -= 1;
    if ($this->throttle_fd_list[$fd]['times'] < 0) {
        if ($this->throttle_close) {
            $server->close($fd, $this->throttle_close == 2 ? true : null);
        }
    } else {
        $this->sendToProcess([
            'event' => 'Message',
            'args' => json_decode(json_encode($args), true),
        ]);
    }
});

$this->on('Close', function (Server $server, ...$args) {

    $fd = $args[0]->fd;
    Timer::clear($this->throttle_fd_list[$fd]['timer']);
    unset($this->throttle_fd_list[$fd]);

    $this->sendToProcess([
        'event' => 'Close',
        'args' => json_decode(json_encode($args), true),
    ]);
});

$this->on('WorkerExit', function (Server $server, ...$args) {
    foreach ($this->throttle_fd_list as $value) {
        Timer::clear($value['timer']);
    }
});
