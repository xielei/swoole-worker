<?php

declare (strict_types = 1);

namespace Xielei\Swoole;

use Exception;
use Swoole\ConnectionPool;
use Swoole\Coroutine;
use Swoole\Coroutine\Server\Connection;
use Swoole\Process;
use Swoole\Server as SwooleServer;
use Swoole\Timer;
use Swoole\WebSocket\Server as WebSocketServer;
use Xielei\Swoole\Interfaces\CmdInterface;
use Xielei\Swoole\Library\Client;
use Xielei\Swoole\Library\Server;

class Gateway extends Service
{
    public $init_file = __DIR__ . '/init/gateway.php';
    public $lan_host = '127.0.0.1';
    public $lan_port = 9018;

    private $register_host;
    private $register_port;
    private $register_secret_key;
    private $register_conn;

    private $listens = [];
    private $cmd_list = [];

    private $router;

    private $lan_server;
    private $process;

    public $worker_pool_list = [];
    public $fd_list = [];
    public $uid_list = [];
    public $group_list = [];

    public function __construct(string $register_host = '127.0.0.1', int $register_port = 9327, string $register_secret_key = '')
    {
        $this->register_host = $register_host;
        $this->register_port = $register_port;
        $this->register_secret_key = $register_secret_key;
        parent::__construct();
    }

    protected function init(SwooleServer $server)
    {

        $process = new Process(function ($process) use ($server) {
            $this->startLanServer($this->lan_host, $this->lan_port);
            $this->connectToRegister($this->lan_host, $this->lan_port);
            $socket = $process->exportSocket();
            while (true) {
                $msg = $socket->recv();
                if (!$msg) {
                    continue;
                }
                $res = unserialize($msg);
                switch ($res['event']) {
                    case Protocol::CLIENT_CONNECT:
                        $this->fd_list[$res['fd']] = [
                            'uid' => '',
                            'session' => [],
                            'group_list' => [],
                            'ws' => isset($server->getClientInfo($res['fd'])['websocket_status']),
                        ];
                        $session_string = serialize([]);
                        $load = pack('CNN', Protocol::CLIENT_CONNECT, $res['fd'], strlen($session_string)) . $session_string;
                        $this->sendToWorker(Protocol::CLIENT_CONNECT, $res['fd'], $load);
                        break;

                    case Protocol::CLIENT_MESSAGE:
                        $bind = $this->fd_list[$res['fd']];
                        $session_string = serialize($bind['session']);
                        $load = pack('CNN', Protocol::CLIENT_MESSAGE, $res['fd'], strlen($session_string)) . $session_string . $res['message'];
                        $this->sendToWorker(Protocol::CLIENT_MESSAGE, $res['fd'], $load);
                        break;

                    case Protocol::CLIENT_WEBSOCKET_CONNECT:
                        $bind = $this->fd_list[$res['fd']];
                        $session_string = serialize($bind['session']);
                        $load = pack('CNN', Protocol::CLIENT_WEBSOCKET_CONNECT, $res['fd'], strlen($session_string)) . $session_string . serialize($res['extra']);
                        $this->sendToWorker(Protocol::CLIENT_WEBSOCKET_CONNECT, $res['fd'], $load);
                        break;

                    case Protocol::CLIENT_CLOSE:
                        $fd = $res['fd'];
                        $bind = $this->fd_list[$fd];
                        $bind['group_list'] = array_values($bind['group_list']);
                        $session_string = serialize($bind['session']);
                        unset($bind['session']);
                        $load = pack('CNN', Protocol::CLIENT_CLOSE, $fd, strlen($session_string)) . $session_string . serialize($bind);
                        $this->sendToWorker(Protocol::CLIENT_CLOSE, $fd, $load);

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

                    default:
                        # code...
                        break;
                }
            }
        }, false, 2, true);
        $this->process = $process;
        $server->addProcess($process);

        $server->on('WorkerStart', function (...$args) {
            include $this->init_file;
            $this->emit('WorkerStart', ...$args);
        });
        foreach (['WorkerExit', 'WorkerStop', 'Connect', 'Receive', 'Close', 'Packet', 'Task', 'Finish', 'PipeMessage'] as $event) {
            $server->on($event, function (...$args) use ($event) {
                $this->emit($event, ...$args);
            });
        }

        foreach ($this->listens as $listen) {
            $port = $server->addListener($listen['host'], $listen['port'], $listen['sockType']);
            $port->set($listen['options']);
            foreach (['Connect', 'Receive', 'Close', 'Open', 'Message'] as $event) {
                $port->on($event, function (...$args) use ($event) {
                    $this->emit('Port' . $event, ...$args);
                });
            }
        }
    }

    private function emit(string $event, ...$args)
    {
        $event = strtolower('on' . $event);
        Service::debug("{$event}");
        call_user_func($this->$event ?: function () {}, ...$args);
    }

    private function on(string $event, callable $callback)
    {
        $event = strtolower('on' . $event);
        $this->$event = $callback;
    }

    public function listen(string $host, int $port, int $sockType = SWOOLE_SOCK_TCP, array $options = [
        'dispatch_mode' => 2,
    ]) {
        $this->listens[] = [
            'host' => $host,
            'port' => $port,
            'sockType' => $sockType,
            'options' => $options,
        ];
    }

    private function sendToProcess($data)
    {
        $this->process->exportSocket()->send(serialize($data));
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

    public function setRouter(callable $callback)
    {
        $this->router = $callback;
    }

    public function getWorkerPool(int $fd, int $cmd): ?ConnectionPool
    {
        if ($this->router) {
            return call_user_func($this->router, $this->worker_pool_list, $fd, $cmd);
        }
        $worker_pool_list = $this->worker_pool_list;
        if ($worker_pool_list) {
            return $worker_pool_list[array_keys($worker_pool_list)[$fd % count($worker_pool_list)]];
        }
        return null;
    }

    public function sendToClient(int $fd, string $message)
    {
        if (isset($this->fd_list[$fd]['ws']) && $this->fd_list[$fd]['ws']) {
            $this->getServer()->send($fd, WebSocketServer::pack($message));
        } else {
            $this->getServer()->send($fd, $message);
        }
    }

    private function sendToWorker(int $cmd, int $fd, string $load = '')
    {
        if ($pool = $this->getWorkerPool($fd, $cmd)) {
            $buff = bin2hex(Protocol::encode($load));
            Service::debug("send to worker:{$buff}");

            $conn = $pool->get();
            $conn->send(Protocol::encode($load));
            $pool->put($conn);
        } else {
            Service::debug("not found worker");
        }
    }

    private function startLanServer(string $host, int $port)
    {
        foreach (glob(__DIR__ . '/Cmd/*.php') as $p) {
            $this->registerCommand(__NAMESPACE__ . '\\Cmd\\' . pathinfo($p, PATHINFO_FILENAME));
        }

        $server = new Server($host, $port);
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
        $this->lan_server = $server;
        $server->start();
    }

    private function connectToRegister(string $lan_host, int $lan_port)
    {
        $client = new Client($this->register_host, $this->register_port);
        $client->onConnect = function (Client $client) use ($lan_host, $lan_port) {
            Service::debug('reg to register');
            $client->send(Protocol::encode(pack('CNn', Protocol::GATEWAY_CONNECT, ip2long($lan_host), $lan_port) . $this->register_secret_key));

            $ping_buffer = Protocol::encode(pack('C', Protocol::PING));
            $client->timer_id = Timer::tick(30000, function () use ($client, $ping_buffer) {
                Service::debug('ping to register');
                $client->send($ping_buffer);
            });
        };
        $client->onClose = function (Client $client) {
            Service::debug('close by register');
            if ($client->timer_id) {
                Timer::clear($client->timer_id);
                unset($client->timer_id);
            }
            Coroutine::sleep(1);
            Service::debug("reconnect to register");
            $client->connect();
        };
        $this->register_conn = $client;
        $client->start();
    }
}
