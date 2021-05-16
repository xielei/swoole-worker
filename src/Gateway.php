<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Exception;
use Swoole\ConnectionPool;
use Swoole\Coroutine\Server\Connection;
use Swoole\Server as SwooleServer;
use Swoole\Timer;
use Swoole\WebSocket\Server as WebSocketServer;
use Xielei\Swoole\Server;

class Gateway extends SwooleServer
{
    public $register_host = '127.0.0.1';
    public $register_port = 3327;
    public $register_secret_key = '';

    public $lan_host = '127.0.0.1';
    public $lan_port_start = 7000;

    public $worker_pool_list = [];

    public $fd_list = [];

    public $uid_list = [];

    public $group_list = [];

    private $cmd_list = [];

    public function start()
    {

        $this->on('connect', function ($server, $fd) {
            $this->fd_list[$fd] = [
                'uid' => '',
                'session' => [],
                'group_list' => [],
                'ws' => isset($this->getClientInfo($fd)['websocket_status']),
            ];
            $this->sendToWorker(Protocol::CLIENT_CONNECT, $fd);
        });

        $this->on('receive', function ($server, $fd, $reactor_id, $message) {
            $this->sendToWorker(Protocol::CLIENT_MESSAGE, $fd, [
                'message' => $message,
            ]);
        });

        $this->on('open', function ($server, $request) {
            $this->sendToWorker(Protocol::CLIENT_WEBSOCKET_CONNECT, $request->fd, [
                'global' => [
                    'header' => $request->header,
                    'server' => $request->server,
                    'get' => $request->get,
                    'post' => $request->post,
                    'cookie' => $request->cookie,
                    'files' => $request->files,
                ],
            ]);
        });

        $this->on('message', function ($server, $frame) {
            switch ($frame->opcode) {
                case WEBSOCKET_OPCODE_TEXT:
                case WEBSOCKET_OPCODE_BINARY:
                    $this->sendToWorker(Protocol::CLIENT_MESSAGE, $frame->fd, [
                        'message' => $frame->data,
                    ]);
                    break;

                default:
                    break;
            }
        });

        $this->on('close', function ($server, $fd) {
            $bind = $this->fd_list[$fd];
            $bind['group_list'] = array_values($bind['group_list']);
            $this->sendToWorker(Protocol::CLIENT_CLOSE, $fd, [
                'bind' => $bind,
            ]);

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
        });

        foreach (glob(__DIR__ . '/Cmd/*.php') as $p) {
            $this->registerCommand(__NAMESPACE__ . '\\Cmd\\' . pathinfo($p, PATHINFO_FILENAME));
        }

        $this->set([
            'dispatch_mode' => 2,
        ]);

        $this->on('WorkerStart', function ($server) {
            $this->startServer($this->lan_host, $this->lan_port_start + $this->worker_id);
            $this->connectToRegister();
        });

        parent::start();
    }

    final public function registerCommand(string $cmd)
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

    public function routeToWorker(int $fd, array $worker_pool_list): ?ConnectionPool
    {
        if ($worker_pool_list) {
            return $worker_pool_list[array_keys($worker_pool_list)[$fd % count($worker_pool_list)]];
        }
        return null;
    }

    final public function sendToWorker(int $cmd, int $fd, array $extra = [])
    {
        if ($pool = $this->routeToWorker($fd, $this->worker_pool_list)) {
            $conn = $pool->get();
            $conn->send(Protocol::encode($cmd, [
                'fd' => intval($fd),
                'session' => $this->fd_list[$fd]['session'],
            ] + $extra));
            $pool->put($conn);
        } else {
            echo "Not found worker\n";
        }
    }

    public function sendToClient(int $fd, string $message)
    {
        if (isset($this->fd_list[$fd]['ws']) && $this->fd_list[$fd]['ws']) {
            $this->send($fd, WebSocketServer::pack($message));
        } else {
            $this->send($fd, $message);
        }
    }

    private function startServer(string $host, int $port)
    {
        $server = new Server($host, $port);
        $server->onMessage = function (Connection $conn, string $buffer) {
            if ($data = unpack("Npack_len/Ccmd", $buffer)) {
                if (isset($this->cmd_list[$data['cmd']])) {
                    call_user_func([$this->cmd_list[$data['cmd']], 'execute'], $this, $conn, substr($buffer, 5));
                } else {
                    $hex_buffer = bin2hex($buffer);
                    echo "cmd:{$data['cmd']} not surport！buffer:{$hex_buffer}\n";
                }
            } else {
                $hex_buffer = bin2hex($buffer);
                echo "unpack failure！buffer:{$hex_buffer}\n";
            }
        };
        $server->onClose = function (Connection $conn) {
            if ($socket = $conn->exportSocket()) {
                if ($peername = $socket->getpeername()) {
                    $address = implode(':', $peername);
                    if (isset($this->worker_pool_list[$address])) {
                        $pool = $this->worker_pool_list[$address];
                        $conn = $pool->get();
                        $conn->close();
                        $pool->close();
                        unset($this->worker_pool_list[$address]);
                    }
                }
            }
        };
        $server->start();
    }

    private function connectToRegister()
    {
        $client = new Client($this->register_host, $this->register_port);
        $client->onConnect = function () use ($client) {
            $client->send(Protocol::encode(Protocol::GATEWAY_CONNECT, [
                'lan_host' => ip2long($this->lan_host),
                'lan_port' => $this->lan_port_start + $this->worker_id,
                'register_secret_key' => $this->register_secret_key,
            ]));

            $ping_buffer = Protocol::encode(Protocol::PING);
            Timer::tick(30000, function () use ($client, $ping_buffer) {
                $client->send($ping_buffer);
            });
        };
        $client->onClose = function () use ($client) {
            Timer::after(1000, function () use ($client) {
                $client->connect();
            });
        };
        $client->start();
    }
}
