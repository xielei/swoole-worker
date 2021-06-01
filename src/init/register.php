<?php

use Swoole\Server;
use Swoole\Timer;
use Xielei\Swoole\Protocol;
use Xielei\Swoole\Register;
use Xielei\Swoole\Service;

/**
 * @var Register $this
 */

$this->on('WorkerExit', function (Server $server, int $workerId) {
    Timer::clearAll();
});
$this->on('WorkerStop', function (Server $server, int $workerId) {
    foreach ($server->connections as $fd) {
        $server->close($fd);
    }
});
$this->on('Receive', function (Server $server, int $fd, int $reactorId, string $buffer) {
    $data = unpack('Ccmd/A*load', Protocol::decode($buffer));
    switch ($data['cmd']) {
        case Protocol::PING:
            break;

        case Protocol::SERVER_RELOAD:
            $server->reload();
            break;

        case Protocol::SERVER_STATUS:
            $status = $server->stats() + [
                'host' => $this->host,
                'port' => $this->port,
                'secret_key' => $this->secret_key,
                'daemonize' => $this->daemonize,
                'log_file' => $this->log_file,
                'pid_file' => $this->pid_file,
                'inner_socket' => $this->inner_socket,
                'gateway_count' => count($this->gateway_address_list),
                'worker_count' => count($this->worker_fd_list),
                'gateway_list' => (function () {
                    $res = [];
                    foreach ($this->gateway_address_list as $key => $value) {
                        $tmp = unpack('Nlan_host/nlan_port', $value);
                        $tmp['lan_host'] = long2ip($tmp['lan_host']);
                        $res[] = $tmp;
                    }
                    return json_encode($res);
                })(),
            ];
            $status['start_time'] = date(DATE_ISO8601, $status['start_time']);
            $load = json_encode($status);
            $server->send($fd, Protocol::encode($load));
            break;

        default:
            Service::debug("onReceive undefined cmd and close");
            $server->close($fd);
            break;
    }
});
$this->on('PortConnect', function (Server $server, int $fd, int $reactorId) {
    Timer::after(3000, function () use ($server, $fd) {
        if (
            isset($this->gateway_address_list[$fd]) ||
            isset($this->worker_fd_list[$fd])
        ) {
            return;
        }
        if ($server->exist($fd)) {
            Service::debug("onPortConnect timeout and close");
            $server->close($fd);
        }
    });
});
$this->on('PortReceive', function (Server $server, int $fd, int $reactorId, string $buffer) {
    $data = unpack('Ccmd/A*load', Protocol::decode($buffer));
    switch ($data['cmd']) {
        case Protocol::GATEWAY_CONNECT:
            $load = unpack('Nlan_host/nlan_port', $data['load']);
            $load['register_secret_key'] = substr($data['load'], 6);
            if ($this->secret_key && $load['register_secret_key'] !== $this->secret_key) {
                Service::debug("onPortReceive GATEWAY_CONNECT close");
                $server->close($fd);
                return;
            }
            $this->gateway_address_list[$fd] = pack('Nn', $load['lan_host'], $load['lan_port']);
            $this->broadcastGatewayAddressList();
            break;

        case Protocol::WORKER_CONNECT:
            if ($this->secret_key && ($data['load'] !== $this->secret_key)) {
                Service::debug("onPortReceive WORKER_CONNECT close");
                $server->close($fd);
                return;
            }
            $this->worker_fd_list[$fd] = $fd;
            $this->broadcastGatewayAddressList($fd);
            break;

        case Protocol::PING:
            break;

        default:
            Service::debug("onPortReceive undefined cmd and close");
            $server->close($fd);
            break;
    }
});
$this->on('PortClose', function (Server $server, int $fd, int $reactorId) {
    if (isset($this->worker_fd_list[$fd])) {
        unset($this->worker_fd_list[$fd]);
    }
    if (isset($this->gateway_address_list[$fd])) {
        unset($this->gateway_address_list[$fd]);
        $this->broadcastGatewayAddressList();
    }
});
