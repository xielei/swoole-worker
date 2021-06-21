<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Swoole\Coroutine\Server\Connection;
use Swoole\Server;
use Xielei\Swoole\Library\SockServer;

class Register extends Service
{
    public $reload_file = __DIR__ . '/reload/register.php';

    protected $inner_server;

    protected $register_host;
    protected $register_port;
    protected $register_secret_key;

    public function __construct(string $register_host = '127.0.0.1', int $register_port = 9327, string $register_secret_key = '')
    {
        parent::__construct();

        $this->register_host = $register_host;
        $this->register_port = $register_port;
        $this->register_secret_key = $register_secret_key;

        $this->inner_server = new SockServer(function (Connection $conn, $data) {
            if (!is_array($data)) {
                return;
            }
            switch (array_shift($data)) {
                case 'status':
                    $ret = [
                        'sw_version' => SW_VERSION,
                    ] + $this->getServer()->stats() + $this->getServer()->setting + [
                        'daemonize' => $this->daemonize,
                        'register_host' => $this->register_host,
                        'register_port' => $this->register_port,
                        'register_secret_key' => $this->register_secret_key,
                        'reload_file' => $this->reload_file,
                        'worker_count' => count($this->globals->get('worker_fd_list', [])),
                        'worker_list' => (function (): string {
                            $res = [];
                            foreach ($this->globals->get('worker_fd_list', []) as $fd) {
                                $res[$fd] = $this->getServer()->getClientInfo($fd);
                            }
                            return json_encode($res);
                        })(),
                        'gateway_count' => count($this->globals->get('gateway_address_list', [])),
                        'gateway_list' => (function () {
                            $res = [];
                            foreach ($this->globals->get('gateway_address_list', []) as $fd => $value) {
                                $tmp = unpack('Nlan_host/nlan_port', $value);
                                $tmp['lan_host'] = long2ip($tmp['lan_host']);
                                $res[$fd] = array_merge($this->getServer()->getClientInfo($fd), $tmp);
                            }
                            return json_encode($res);
                        })(),
                    ];
                    $ret['start_time'] = date(DATE_ISO8601, $ret['start_time']);
                    SockServer::sendToConn($conn, $ret);
                    break;

                case 'reload':
                    $this->getServer()->reload();
                    break;

                default:
                    break;
            }
        }, '/var/run/' . str_replace('/', '_', array_pop(debug_backtrace())['file']) . '.sock');

        $this->addCommand('status', 'status', 'displays the running status of the service', function (array $args): int {
            if (!$this->isRun()) {
                fwrite(STDOUT, "the service is not running!\n");
                return self::PANEL_LISTEN;
            }
            $fp = stream_socket_client("unix://{$this->inner_server->getSockFile()}", $errno, $errstr);
            if (!$fp) {
                fwrite(STDOUT, "ERROR: $errno - $errstr\n");
            } else {
                fwrite($fp, Protocol::encode(serialize(['status'])));
                $res = unserialize(Protocol::decode(fread($fp, 40960)));
                foreach ($res as $key => $value) {
                    fwrite(STDOUT, str_pad((string) $key, 25, '.', STR_PAD_RIGHT) . ' ' . $value . "\n");
                }
                fclose($fp);
            }
            return self::PANEL_LISTEN;
        });
    }

    protected function createServer(): Server
    {
        $server = new Server($this->register_host, $this->register_port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $this->inner_server->mountTo($server);
        $this->set([
            'heartbeat_idle_time' => 60,
            'heartbeat_check_interval' => 3,

            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 0,
        ]);
        return $server;
    }

    protected function broadcastGatewayAddressList(int $fd = null)
    {
        $load = pack('C', Protocol::BROADCAST_GATEWAY_ADDRESS_LIST) . implode('', $this->globals->get('gateway_address_list', []));
        $buffer = Protocol::encode($load);
        if ($fd) {
            $this->getServer()->send($fd, $buffer);
        } else {
            foreach ($this->globals->get('worker_fd_list', []) as $fd => $info) {
                $this->getServer()->send($fd, $buffer);
            }
        }

        $addresses = [];
        foreach ($this->globals->get('gateway_address_list', []) as $value) {
            $tmp = unpack('Nhost/nport', $value);
            $tmp['host'] = long2ip($tmp['host']);
            $addresses[] = $tmp;
        }
        $addresses = json_encode($addresses);
        if ($fd) {
            Service::debug("broadcastGatewayAddressList fd:{$fd} addresses:{$addresses}");
        } else {
            Service::debug("broadcastGatewayAddressList addresses:{$addresses}");
        }
    }
}