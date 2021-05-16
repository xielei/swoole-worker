<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Swoole\Server;
use Swoole\Timer;
use Throwable;

class Register extends Server
{
    protected $gateway_fd_list = [];
    protected $worker_fd_list = [];
    public $secret_key = '';

    public function __construct(string $host = '127.0.0.1', int $port = 3327)
    {
        parent::__construct($host, $port, SWOOLE_BASE);
    }

    final public function start()
    {
        $this->on('connect', function ($server, $fd) {
            Timer::after(3000, function () use ($server, $fd) {
                if (isset($this->gateway_fd_list[$fd]) || isset($this->worker_fd_list[$fd])) {
                    return;
                }
                if ($server->exist($fd)) {
                    $server->close($fd);
                }
            });
        });

        $this->on('receive', function ($server, $fd, $reactor_id, $buffer) {

            try {
                $data = Protocol::decode($buffer);
            } catch (Throwable $th) {
                $hex_buffer = bin2hex($buffer);
                echo "Protocol::decode failure! buffer:{$hex_buffer}\n";
                return;
            }

            switch ($data['cmd']) {
                case Protocol::GATEWAY_CONNECT:
                    if ($this->secret_key && $data['register_secret_key'] !== $this->secret_key) {
                        $server->close($fd);
                        return;
                    }
                    $this->gateway_fd_list[$fd] = pack('Nn', $data['lan_host'], $data['lan_port']);
                    $this->broadcastAddresses($server);
                    break;

                case Protocol::WORKER_CONNECT:
                    if ($this->secret_key && $data['register_secret_key'] !== $this->secret_key) {
                        $server->close($fd);
                        return;
                    }
                    $this->worker_fd_list[$fd] = $fd;
                    $this->broadcastAddresses($server, $fd);
                    break;

                case Protocol::PING:
                    break;

                default:
                    $server->close($fd);
                    break;
            }
        });

        $this->on('close', function ($server, $fd) {
            if (isset($this->worker_fd_list[$fd])) {
                unset($this->worker_fd_list[$fd]);
            }
            if (isset($this->gateway_fd_list[$fd])) {
                unset($this->gateway_fd_list[$fd]);
                $this->broadcastAddresses($server);
            }
        });

        $this->set([
            'worker_num' => 1,

            'open_length_check' => true,
            'package_max_length' => 81920,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 0,

            'open_tcp_keepalive' => true,
            'tcp_keepidle' => 4,
            'tcp_keepinterval' => 1,
            'tcp_keepcount' => 5,

            'heartbeat_idle_time' => 60,
            'heartbeat_check_interval' => 6,
        ]);

        parent::start();
    }

    private function broadcastAddresses($server, $fd = null)
    {
        $buffer = Protocol::encode(Protocol::BROADCAST_ADDRESS_LIST, [
            'addresses' => implode('', $this->gateway_fd_list),
        ]);

        if ($fd) {
            $server->send($fd, $buffer);
        } else {
            foreach ($this->worker_fd_list as $fd => $info) {
                $server->send($fd, $buffer);
            }
        }
    }
}
