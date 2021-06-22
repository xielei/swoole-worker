<?php

use Swoole\Server;
use Swoole\Server\Event;
use Xielei\Swoole\Protocol;
use Swoole\Timer;
use Xielei\Swoole\Register;
use Xielei\Swoole\Service;

/**
 * @var Register $this
 */

$this->on('Connect', function (Server $server, Event $event) {
    Timer::after(3000, function () use ($server, $event) {
        if (
            $this->globals->isset('gateway_address_list.' . $event->fd) ||
            $this->globals->isset('worker_fd_list.' . $event->fd)
        ) {
            return;
        }
        if ($server->exist($event->fd)) {
            Service::debug("close timeout fd:{$event->fd}");
            $server->close($event->fd);
        }
    });
});

$this->on('Receive', function (Server $server, Event $event) {
    $data = unpack('Ccmd/A*load', Protocol::decode($event->data));
    switch ($data['cmd']) {
        case Protocol::GATEWAY_CONNECT:
            $load = unpack('Nlan_host/nlan_port', $data['load']);
            $load['register_secret_key'] = substr($data['load'], 6);
            if ($this->register_secret_key && $load['register_secret_key'] !== $this->register_secret_key) {
                Service::debug("GATEWAY_CONNECT failure. secret_key invalid~");
                $server->close($event->fd);
                return;
            }
            $this->globals->set('gateway_address_list.' . $event->fd, pack('Nn', $load['lan_host'], $load['lan_port']));
            $this->broadcastGatewayAddressList();
            break;

        case Protocol::WORKER_CONNECT:
            if ($this->register_secret_key && ($data['load'] !== $this->register_secret_key)) {
                Service::debug("WORKER_CONNECT failure. secret_key invalid~");
                $server->close($event->fd);
                return;
            }
            $this->globals->set('worker_fd_list.' . $event->fd, $event->fd);
            $this->broadcastGatewayAddressList($event->fd);
            break;

        case Protocol::PING:
            break;

        default:
            Service::debug("undefined cmd and closed by register");
            $server->close($event->fd);
            break;
    }
});

$this->on('Close', function (Server $server, Event $event) {
    if ($this->globals->isset('worker_fd_list.' . $event->fd)) {
        $this->globals->unset('worker_fd_list.' . $event->fd);
    }
    if ($this->globals->isset('gateway_address_list.' . $event->fd)) {
        $this->globals->unset('gateway_address_list.' . $event->fd);
        $this->broadcastGatewayAddressList();
    }
});
