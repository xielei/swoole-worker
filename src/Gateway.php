<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Exception;
use Swoole\Coroutine;
use Swoole\Coroutine\Server\Connection;
use Swoole\Process;
use Swoole\Server as SwooleServer;
use Swoole\Timer;
use Swoole\WebSocket\Server as WebSocketServer;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Library\Client;
use Xielei\Swoole\Library\Server;
use Xielei\Swoole\Library\SockServer;

class Gateway extends Service
{
    public $reload_file = __DIR__ . '/reload/gateway.php';

    public $register_host = '127.0.0.1';
    public $register_port = 9327;
    public $register_secret_key = '';

    public $lan_host = '127.0.0.1';
    public $lan_port = 9108;

    public $router;

    protected $inner_server;

    protected $process;
    protected $cmd_list = [];

    public $worker_pool_list = [];
    public $fd_list = [];
    public $uid_list = [];
    public $group_list = [];

    private $listen_list = [];

    public function __construct()
    {
        parent::__construct();

        $this->router = function (int $fd, int $cmd) {
            if ($this->worker_pool_list) {
                return $this->worker_pool_list[array_keys($this->worker_pool_list)[$fd % count($this->worker_pool_list)]];
            }
        };

        $this->inner_server = new SockServer(function (Connection $conn, $data) {
            if (!is_array($data)) {
                return;
            }
            switch (array_shift($data)) {
                case 'status':
                    $ret = $this->getServer()->stats() + $this->getServer()->setting + [
                        'daemonize' => $this->daemonize,
                        'register_host' => $this->register_host,
                        'register_port' => $this->register_port,
                        'register_secret_key' => $this->register_secret_key,
                        'lan_host' => $this->lan_host,
                        'lan_port' => $this->lan_port,
                        'reload_file' => $this->reload_file,
                        'listen_list' => json_encode($this->listen_list),
                        'cmd_list' => (function (): string {
                            $cmd_list = $this->cmd_list;
                            foreach (glob(__DIR__ . '/Cmd/*.php') as $filename) {
                                $cls_name = __NAMESPACE__ . '\\Cmd\\' . pathinfo($filename, PATHINFO_FILENAME);
                                $cmd_list[$cls_name::getCommandCode()] = $cls_name;
                            }
                            return json_encode($cmd_list);
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

    protected function createServer(): SwooleServer
    {
        $server = new WebSocketServer('127.0.0.1', 0, SWOOLE_BASE);

        foreach ($this->listen_list as $listen) {
            $port = $server->addListener($listen['host'], $listen['port'], $listen['sockType']);
            $port->set($listen['options']);
            if (isset($listen['options']['open_websocket_protocol']) && $listen['options']['open_websocket_protocol']) {
                $port->on('Connect', function () {
                });
                $port->on('Request', function ($request, $response) {
                    $response->status(403);
                    $response->end("Not Supported~\n");
                });
            }
        }

        $this->inner_server->mountTo($server);
        $this->process = new Process(function ($process) use ($server) {
            $this->connectToRegister();
            $this->startLanServer();
            Coroutine::create(function () use ($process) {
                $socket = $process->exportSocket();
                $socket->setProtocol([
                    'open_length_check' => true,
                    'package_length_type' => 'N',
                    'package_length_offset' => 0,
                    'package_body_offset' => 0,
                ]);
                while (true) {
                    $buffer = $socket->recv();
                    if (!$buffer) {
                        continue;
                    }
                    $res = unserialize($buffer);
                    switch ($res['event']) {
                        case 'Connect':
                            list($fd) = $res['args'];
                            $this->fd_list[$fd] = [
                                'uid' => '',
                                'session' => [],
                                'group_list' => [],
                                'ws' => 0,
                            ];
                            $session_string = serialize([]);
                            $load = pack('CNN', Protocol::EVENT_CONNECT, $fd, strlen($session_string)) . $session_string;
                            $this->sendToWorker(Protocol::EVENT_CONNECT, $fd, $load);
                            break;

                        case 'Receive':
                            list($fd, $reactorId,  $data) = $res['args'];
                            $bind = $this->fd_list[$fd];
                            $session_string = serialize($bind['session']);
                            $load = pack('CNN', Protocol::EVENT_RECEIVE, $fd, strlen($session_string)) . $session_string . $data;
                            $this->sendToWorker(Protocol::EVENT_RECEIVE, $fd, $load);
                            break;

                        case 'Close':
                            list($fd) = $res['args'];
                            if (!isset($this->fd_list[$fd])) {
                                continue;
                            }
                            $bind = $this->fd_list[$fd];
                            $bind['group_list'] = array_values($bind['group_list']);
                            $session_string = serialize($bind['session']);
                            unset($bind['session']);
                            $load = pack('CNN', Protocol::EVENT_CLOSE, $fd, strlen($session_string)) . $session_string . serialize($bind);
                            $this->sendToWorker(Protocol::EVENT_CLOSE, $fd, $load);

                            if ($bind_uid = $this->fd_list[$fd]['uid']) {
                                unset($this->uid_list[$bind_uid][$fd]);
                                if (!$this->uid_list[$bind_uid]) {
                                    unset($this->uid_list[$bind_uid]);
                                }
                            }

                            foreach ($this->fd_list[$fd]['group_list'] as $bind_group) {
                                unset($this->group_list[$bind_group][$fd]);
                                if (!$this->group_list[$bind_group]) {
                                    unset($this->group_list[$bind_group]);
                                }
                            }

                            unset($this->fd_list[$fd]);
                            break;

                        case 'Open':
                            list($request) = $res['args'];
                            $this->fd_list[$request['fd']] = [
                                'uid' => '',
                                'session' => [],
                                'group_list' => [],
                                'ws' => 1,
                            ];
                            $session_string = serialize([]);
                            $load = pack('CNN', Protocol::EVENT_OPEN, $request['fd'], strlen($session_string)) . $session_string . serialize($request);
                            $this->sendToWorker(Protocol::EVENT_OPEN, $request['fd'], $load);
                            break;

                        case 'Message':
                            list($frame) = $res['args'];
                            $bind = $this->fd_list[$frame['fd']];
                            $session_string = serialize($bind['session']);
                            $load = pack('CNN', Protocol::EVENT_MESSAGE, $frame['fd'], strlen($session_string)) . $session_string . serialize($frame);
                            $this->sendToWorker(Protocol::EVENT_MESSAGE, $frame['fd'], $load);
                            break;

                        default:
                            Service::debug("undefined event. buffer:{$buffer}");
                            break;
                    }
                    Coroutine::sleep(0.001);
                }
            });
        }, false, 1, true);
        $server->addProcess($this->process);
        return $server;
    }

    public function listen(string $host, int $port, array $options = [], int $sockType = SWOOLE_SOCK_TCP)
    {
        $this->listen_list[$host . ':' . $port] = [
            'host' => $host,
            'port' => $port,
            'sockType' => $sockType,
            'options' => $options,
        ];
    }

    protected function sendToProcess($data)
    {
        $this->process->exportSocket()->send(serialize($data));
    }

    public function sendToClient(int $fd, string $message)
    {
        if (isset($this->fd_list[$fd]['ws']) && $this->fd_list[$fd]['ws']) {
            $this->getServer()->send($fd, WebSocketServer::pack($message));
        } else {
            $this->getServer()->send($fd, $message);
        }
    }

    protected function sendToWorker(int $cmd, int $fd, string $load)
    {
        if ($pool = call_user_func($this->router, $fd, $cmd)) {
            $conn = $pool->get();
            $conn->send(Protocol::encode($load));
            $pool->put($conn);

            $buff = bin2hex(Protocol::encode($load));
            Service::debug("send to worker:{$buff}");
        } else {
            Service::debug("worker not found");
        }
    }

    public function registerCommand(string $cmd)
    {
        if (is_a($cmd, CmdInterface::class, true)) {
            if (isset($this->cmd_list[$cmd::getCommandCode()])) {
                throw new Exception("registerCommand failure! cmd:{$cmd::getCommandCode()} was registed.");
            } else {
                $this->cmd_list[$cmd::getCommandCode()] = $cmd;
            }
        } else {
            throw new Exception('cmd must instanceof Cmdinterface');
        }
    }

    protected function startLanServer()
    {
        foreach (glob(__DIR__ . '/Cmd/*.php') as $filename) {
            $this->registerCommand(__NAMESPACE__ . '\\Cmd\\' . pathinfo($filename, PATHINFO_FILENAME));
        }
        Service::debug('start to startLanServer');
        $server = new Server($this->lan_host, $this->lan_port);
        $server->onConnect = function (Connection $conn) {
            $conn->peername = $conn->exportSocket()->getpeername();
        };
        $server->onMessage = function (Connection $conn, string $buffer) {
            $load = Protocol::decode($buffer);
            $data = unpack("Ccmd", $load);
            if (isset($this->cmd_list[$data['cmd']])) {
                call_user_func([$this->cmd_list[$data['cmd']], 'execute'], $this, $conn, substr($load, 1));
            } else {
                $hex_buffer = bin2hex($buffer);
                Service::debug("cmd:{$data['cmd']} not surport! buffer:{$hex_buffer}");
            }
        };
        $server->onClose = function (Connection $conn) {
            $address = implode(':', $conn->peername);
            if (isset($this->worker_pool_list[$address])) {
                Service::debug("close worker client {$address}");
                $pool = $this->worker_pool_list[$address];
                $conn = $pool->get();
                $conn->close();
                $pool->close();
                unset($this->worker_pool_list[$address]);
            } else {
                Service::debug("close no reg worker client {$address}");
            }
        };
        $server->start();
    }

    protected function connectToRegister()
    {
        Service::debug('start to connectToRegister');
        $client = new Client($this->register_host, $this->register_port);
        $client->onConnect = function () use ($client) {
            Service::debug('reg to register');
            $client->send(Protocol::encode(pack('CNn', Protocol::GATEWAY_CONNECT, ip2long($this->lan_host), $this->lan_port) . $this->register_secret_key));

            $ping_buffer = Protocol::encode(pack('C', Protocol::PING));
            $client->timer_id = Timer::tick(30000, function () use ($client, $ping_buffer) {
                Service::debug('ping to register');
                $client->send($ping_buffer);
            });
        };
        $client->onClose = function () use ($client) {
            Service::debug('close by register');
            if ($client->timer_id) {
                Timer::clear($client->timer_id);
                unset($client->timer_id);
            }
            Coroutine::sleep(1);
            Service::debug("reconnect to register");
            $client->connect();
        };
        $client->start();
    }
}
