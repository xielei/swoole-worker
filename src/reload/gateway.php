<?php

use Swoole\Server;
use Swoole\Timer;
use Xielei\Swoole\Gateway;

/**
 * @var Gateway $this
 */

foreach (['Connect', 'Open', 'Receive', 'Message'] as $event) {
    $this->on($event, function (Server $server, ...$args) use ($event) {

        if ($this->throttle) {
            $info = $server->getClientInfo($args[0]->fd, $args[0]->reactor_id, true);
            if (is_array($info) && isset($info['remote_ip'])) {
                $ip = $info['remote_ip'];
                if (!isset($this->throttle_list[$ip])) {
                    $this->throttle_list[$ip] = [
                        'timer' => Timer::tick($this->throttle_interval, function () use ($ip) {
                            if ($this->throttle_list[$ip]['fd_list']) {
                                $this->throttle_list[$ip]['times'] = $this->throttle_times;
                            } else {
                                Timer::clear($this->throttle_list[$ip]['timer']);
                                unset($this->throttle_list[$ip]);
                            }
                        }),
                        'times' => $this->throttle_times,
                        'fd_list' => [],
                    ];
                }
                if (!isset($this->throttle_list[$ip]['fd_list'][$args[0]->fd])) {
                    $this->throttle_list[$ip]['fd_list'][$args[0]->fd] = $args[0]->fd;
                }
                $this->throttle_list[$ip]['times'] -= 1;
                if ($this->throttle_list[$ip]['times'] < 0) {
                    switch ($this->throttle_close) {
                        case 1:
                        case 2:
                            $server->close($args[0]->fd, $this->throttle_close == 2 ? true : null);
                            break;

                        case 3:
                        case 4:
                            foreach ($this->throttle_list[$ip]['fd_list'] as $value) {
                                $server->close($value, $this->throttle_close == 4 ? true : null);
                            }
                            break;

                        default:
                            break;
                    }
                    return;
                }
            }
        }

        $this->sendToProcess([
            'event' => $event,
            'args' => json_decode(json_encode($args), true),
        ]);
    });
}

$this->on('Close', function (Server $server, ...$args) {

    if ($this->throttle) {
        $info = $server->getClientInfo($args[0]->fd, $args[0]->reactor_id, true);
        if (is_array($info) && isset($info['remote_ip'])) {
            $ip = $info['remote_ip'];
            unset($this->throttle_list[$ip]['fd_list'][$args[0]->fd]);
        }
    }

    $this->sendToProcess([
        'event' => 'Close',
        'args' => json_decode(json_encode($args), true),
    ]);
});

$this->on('WorkerExit', function (Server $server, ...$args) {
    foreach ($this->throttle_list as $value) {
        Timer::clear($value['timer']);
    }
});
