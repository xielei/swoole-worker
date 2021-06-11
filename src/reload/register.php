<?php

use Swoole\Server;
use Xielei\Swoole\Protocol;
use Swoole\Timer;
use Xielei\Swoole\Register;
use Xielei\Swoole\Service;

/**
 * @var Register $this
 */

$this->on('Connect', function (Server $server, int $fd, int $reactorId) {
    Timer::after(3000, function () use ($server, $fd) {
        if (
            $this->globals->isset('gateway_address_list.' . $fd) ||
            $this->globals->isset('worker_fd_list.' . $fd)
        ) {
            return;
        }
        Service::debug("close timeout fd:{$fd}");
        $server->close($fd);
    });
});

$this->on('Receive', function (Server $server, int $fd, int $reactorId, string $buffer) {
    $data = unpack('Ccmd/A*load', Protocol::decode($buffer));
    switch ($data['cmd']) {
        case Protocol::GATEWAY_CONNECT:
            $load = unpack('Nlan_host/nlan_port', $data['load']);
            $load['register_secret_key'] = substr($data['load'], 6);
            if ($this->register_secret_key && $load['register_secret_key'] !== $this->register_secret_key) {
                Service::debug("GATEWAY_CONNECT failure. secret_key invalid~");
                $server->close($fd);
                return;
            }
            $this->globals->set('gateway_address_list.' . $fd, pack('Nn', $load['lan_host'], $load['lan_port']));
            $this->broadcastGatewayAddressList();
            break;

        case Protocol::WORKER_CONNECT:
            if ($this->register_secret_key && ($data['load'] !== $this->register_secret_key)) {
                Service::debug("WORKER_CONNECT failure. secret_key invalid~");
                $server->close($fd);
                return;
            }
            $this->globals->set('worker_fd_list.' . $fd, $fd);
            $this->broadcastGatewayAddressList($fd);
            break;

        case Protocol::PING:
            break;

        default:
            Service::debug("undefined cmd and closed by register");
            $server->close($fd);
            break;
    }
});

$this->on('Close', function (Server $server, int $fd) {
    if ($this->globals->isset('worker_fd_list.' . $fd)) {
        $this->globals->unset('worker_fd_list.' . $fd);
    }
    if ($this->globals->isset('gateway_address_list.' . $fd)) {
        $this->globals->unset('gateway_address_list.' . $fd);
        $this->broadcastGatewayAddressList();
    }
});
