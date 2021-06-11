<?php

use Swoole\Server;
use Xielei\Swoole\Worker;

/**
 * @var Worker $this
 */

foreach (['Connect', 'Receive', 'Close', 'Open', 'Message'] as $event) {
    $this->on($event, function (Server $server, ...$args) use ($event) {
        $this->sendToProcess([
            'event' => $event,
            'args' => json_decode(json_encode($args), true),
        ]);
    });
}
