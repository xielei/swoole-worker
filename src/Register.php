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
            echo "DEBUG 客户连接 fd:{$fd}\n";
            // 3秒后没有认证就断开连接
            Timer::after(3000, function () use ($server, $fd) {
                if (isset($this->gateway_fd_list[$fd]) || isset($this->worker_fd_list[$fd])) {
                    return;
                }
                if ($server->exist($fd)) {
                    echo "DEBUG 未认证断开 fd:{$fd}\n";
                    $server->close($fd);
                }
            });
        });

        $this->on('receive', function ($server, $fd, $reactor_id, $buffer) {

            try {
                $data = Protocol::decode($buffer);
            } catch (Throwable $th) {
                $hex_buffer = bin2hex($buffer);
                echo "DEBUG 解码失败 fd:{$fd} buffer:{$hex_buffer}\n";
                return;
            }

            switch ($data['cmd']) {
                case Protocol::GATEWAY_CONNECT:

                    echo "DEBUG 收到消息 fd:{$fd} Protocol::GATEWAY_CONNECT\n";

                    if ($this->secret_key && $data['register_secret_key'] !== $this->secret_key) {
                        $server->close($fd);
                        return;
                    }
                    $this->gateway_fd_list[$fd] = pack('Nn', $data['lan_host'], $data['lan_port']);
                    $this->broadcastAddresses($server);
                    break;

                case Protocol::WORKER_CONNECT:

                    echo "DEBUG 收到消息 fd:{$fd} Protocol::WORKER_CONNECT\n";

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
                    echo "DEBUG 未知消息 cmd:{$data['cmd']} fd:{$fd} 关闭连接\n";
                    $server->close($fd);
                    break;
            }
        });

        $this->on('close', function ($server, $fd) {
            if (isset($this->worker_fd_list[$fd])) {
                echo "DEBUG Worker 连接断开 fd:{$fd}\n";
                unset($this->worker_fd_list[$fd]);
            }
            if (isset($this->gateway_fd_list[$fd])) {
                echo "DEBUG Gateway 连接断开 fd:{$fd}\n";
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
            'tcp_keepidle' => 4, //4s没有数据传输就进行检测
            'tcp_keepinterval' => 1, //1s探测一次
            'tcp_keepcount' => 5, //探测的次数，超过5次后还没回包close此连接

            'heartbeat_idle_time' => 60, // 表示一个连接如果60秒内未向服务器发送任何数据，此连接将被强制关闭
            'heartbeat_check_interval' => 6, // 表示每6秒遍历一次
        ]);

        parent::start();
    }

    private function broadcastAddresses($server, $fd = null)
    {
        $buffer = Protocol::encode(Protocol::BROADCAST_ADDRESS_LIST, [
            'addresses' => implode('', $this->gateway_fd_list),
        ]);

        if ($fd) {

            $hex_buffer = bin2hex($buffer);
            echo "DEBUG 广播地址给fd:{$fd} buffer:{$hex_buffer}\n";

            $server->send($fd, $buffer);
        } else {

            $hex_buffer = bin2hex($buffer);
            echo "DEBUG 广播地址给全局 buffer:{$hex_buffer}\n";

            foreach ($this->worker_fd_list as $fd => $info) {
                $server->send($fd, $buffer);
            }
        }
    }
}
