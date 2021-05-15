<?php

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

    // 保存工作客户端的连接信息
    public $worker_pool_list = [];

    // 保存客户的信息
    public $fd_list = [];

    // 保存客户与用户id的关联信息
    public $uid_list = [];

    // 保存客户与分组的关联信息
    public $group_list = [];

    // 保存客户与标签的关联信息
    public $tag_list = [];

    private $cmd_list = [];

    public function start()
    {
        // tcp链接
        $this->on('connect', function ($server, $fd) {
            echo "DEBUG 客户链接 fd:{$fd}\n";
            $this->fd_list[$fd] = [
                'uid' => null,
                'session' => null,
                'group_list' => [],
                'tag_list' => [],
                'ws' => isset($this->getClientInfo($fd)['websocket_status']),
            ];
            $this->sendToWorker(Protocol::CLIENT_CONNECT, $fd);
        });

        // tcp消息
        $this->on('receive', function ($server, $fd, $reactor_id, $message) {
            echo "DEBUG 客户消息 fd:{$fd} message:{$message}\n";
            $this->sendToWorker(Protocol::CLIENT_MESSAGE, $fd, [
                'message' => $message,
            ]);
        });

        // websocket链接
        $this->on('open', function ($server, $request) {
            echo "DEBUG Websocket链接 fd:{$request->fd}\n";
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

        // websocket消息
        $this->on('message', function ($server, $frame) {
            echo "DEBUG Websocket消息 fd:{$frame->fd} data:{$frame->data}\n";
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

        // 断开链接
        $this->on('close', function ($server, $fd) {
            echo "DEBUG 客户关闭 fd:{$fd}\n";
            $this->sendToWorker(Protocol::CLIENT_CLOSE, $fd, [
                'bind' => $this->fd_list[$fd],
            ]);
            // 删除用户绑定
            if ($bind_uid = $this->fd_list[$fd]['uid']) {
                unset($this->uid_list[$bind_uid][$fd]);
                if (!$this->uid_list[$bind_uid]) {
                    unset($this->uid_list[$bind_uid]);
                }
            }
            // 删除分组绑定
            foreach ($this->fd_list[$fd]['group_list'] as $bind_group) {
                unset($this->group_list[$bind_group][$fd]);
                if (!$this->group_list[$bind_group]) {
                    unset($this->group_list[$bind_group]);
                }
            }
            // 删除标签绑定
            foreach ($this->fd_list[$fd]['tag_list'] as $bind_tag) {
                unset($this->tag_list[$bind_tag][$fd]);
                if (!$this->tag_list[$bind_tag]) {
                    unset($this->tag_list[$bind_tag]);
                }
            }
            // 删除绑定数据
            unset($this->fd_list[$fd]);
        });

        foreach (glob(__DIR__ . '/Cmd/*.php') as $p) {
            $this->registerCommand(__NAMESPACE__ . '\\Cmd\\' . pathinfo($p, PATHINFO_FILENAME));
        }

        $this->set([
            'dispatch_mode' => 2,
        ]);

        $this->on('WorkerStart', function ($server) {
            echo "DEBUG WorkerStart worker_id:{$this->worker_id}\n";
            // 开启内部服务链接
            $this->startServer($this->lan_host, $this->lan_port_start + $this->worker_id);
            // 连接register
            $this->connectToRegister();
        });

        parent::start();
    }

    final public function registerCommand(string $cmd)
    {
        if (is_a($cmd, CmdInterface::class, true)) {
            if (isset($this->cmd_list[$cmd::getCommandCode()])) {
                throw new Exception("命令代码:{$cmd::getCommandCode()} 已使用！");
            } else {
                $this->cmd_list[$cmd::getCommandCode()] = $cmd;
            }
        } else {
            throw new Exception('命令必须继承Cmdinterface接口！');
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
            echo "DEBUG worker 客户端未连接\n";
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
                    echo "DEBUG 命令:{$data['cmd']}不支持！buffer:{$hex_buffer}\n";
                }
            } else {
                echo "DEBUG 内部链接服务器 数据解码失败！";
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

            $errCode = swoole_last_error();
            $errMsg = socket_strerror($errCode);
            echo "DEBUG 内部链接关闭 address:{$address} errCode:{$errCode}, errMsg:{$errMsg}\n";
        };
        $server->start();
    }

    private function connectToRegister()
    {
        $client = new Client($this->register_host, $this->register_port);
        $client->onStart = function () {
            echo "DEBUG start..9idf\n";
        };
        $client->onConnect = function () use ($client) {
            echo "DEBUG register 连接成功\n";
            $client->send(Protocol::encode(Protocol::GATEWAY_CONNECT, [
                'lan_host' => ip2long($this->lan_host),
                'lan_port' => $this->lan_port_start + $this->worker_id,
                'register_secret_key' => $this->register_secret_key,
            ]));
            echo "DEBUG register 注册完成\n";

            $ping_buffer = Protocol::encode(Protocol::PING);
            Timer::tick(30000, function () use ($client, $ping_buffer) {
                $client->send($ping_buffer);
            });
        };
        $client->onClose = function () use ($client) {
            echo "DEBUG close..\n";
            Timer::after(1000, function () use ($client) {
                echo "DEBUG 重新链接...\n";
                $client->connect();
            });
        };
        $client->onError = function ($errCode) use ($client) {
            // if ($errCode !== SOCKET_ETIMEDOUT) {
            //     echo "DEBUG onError..9idf\n";
            //     $client->close(true);
            // }
        };
        $client->start();
    }
}
