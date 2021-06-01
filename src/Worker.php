<?php

declare (strict_types = 1);

namespace Xielei\Swoole;

use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Server as SwooleServer;
use Swoole\Timer;
use Xielei\Swoole\Cmd\Ping;
use Xielei\Swoole\Cmd\RegisterWorker;
use Xielei\Swoole\Library\Client;

class Worker extends Service
{
    public $init_file = __DIR__ . '/init/worker.php';
    public $worker_file = __DIR__ . '/init/event_worker.php';
    public $task_file = __DIR__ . '/init/event_task.php';

    private $register_host;
    private $register_port;
    private $register_secret_key;
    private $register_conn;

    private $process;

    private $gateway_address_list = [];
    private $gateway_conn_list = [];

    public function __construct(string $register_host = '127.0.0.1', int $register_port = 9327, string $register_secret_key = '')
    {
        $this->register_host = $register_host;
        $this->register_port = $register_port;
        $this->register_secret_key = $register_secret_key;
        parent::__construct();
    }

    protected function init(SwooleServer $server)
    {
        $server->set([
            'task_worker_num' => 1,
            'task_enable_coroutine' => true,
        ]);

        $process = new Process(function ($process) {
            $this->connectToRegister();
            $socket = $process->exportSocket();
            while (true) {
                $msg = $socket->recv();
                if (!$msg) {
                    continue;
                }
                $res = unserialize($msg);
                switch ($res['event']) {
                    case 'gateway_address_list':
                        $this->getServer()->sendMessage(serialize([
                            'event' => 'gateway_address_list',
                            'gateway_address_list' => $this->gateway_address_list,
                        ]), $res['worker_id']);
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

    private function sendToProcess($data)
    {
        $this->process->exportSocket()->send(serialize($data));
    }

    private function connectToRegister()
    {
        $client = new Client($this->register_host, $this->register_port);
        $client->onConnect = function (Client $client) {
            Service::debug("connect to register");
            $client->send(Protocol::encode(pack('C', Protocol::WORKER_CONNECT) . $this->register_secret_key));

            $ping_buffer = Protocol::encode(pack('C', Protocol::PING));
            $client->timer_id = Timer::tick(30000, function () use ($client, $ping_buffer) {
                Service::debug("send ping to register");
                $client->send($ping_buffer);
            });
        };
        $client->onMessage = function (string $buffer) {
            Service::debug("receive message from register");
            $data = unpack('Ccmd/A*load', Protocol::decode($buffer));
            switch ($data['cmd']) {
                case Protocol::BROADCAST_GATEWAY_ADDRESS_LIST:
                    $addresses = [];
                    if ($data['load'] && (strlen($data['load']) % 6 === 0)) {
                        foreach (str_split($data['load'], 6) as $value) {
                            $address = unpack('Nlan_host/nlan_port', $value);
                            $address['lan_host'] = long2ip($address['lan_host']);
                            $addresses[$address['lan_host'] . ':' . $address['lan_port']] = $address;
                        }
                    }
                    $this->gateway_address_list = $addresses;

                    for ($i = 0; $i < $this->getServer()->setting['worker_num'] + $this->getServer()->setting['task_worker_num']; $i++) {
                        $this->getServer()->sendMessage(serialize([
                            'event' => 'gateway_address_list',
                            'gateway_address_list' => $this->gateway_address_list,
                        ]), $i);
                    }

                    $new_address_list = array_diff_key($this->gateway_address_list, $this->gateway_conn_list);
                    foreach ($new_address_list as $key => $address) {
                        $client = new Client($address['lan_host'], $address['lan_port']);
                        $client->onConnect = function () use ($client, $address) {
                            Service::debug("connect to gateway {$address['lan_host']}:{$address['lan_port']} 成功");
                            $client->send(Protocol::encode(pack('C', RegisterWorker::getCommandCode())));

                            $ping_buffer = Protocol::encode(pack('C', Ping::getCommandCode()));
                            $client->timer_id = Timer::tick(30000, function () use ($client, $ping_buffer, $address) {
                                Service::debug("send ping to gateway {$address['lan_host']}:{$address['lan_port']}");
                                $client->send($ping_buffer);
                            });
                        };
                        $client->onMessage = function (string $buffer) use ($address) {
                            $this->getServer()->sendMessage(serialize([
                                'event' => 'gateway_event',
                                'buffer' => $buffer,
                                'address' => $address,
                            ]), $address['port'] % $this->getServer()->setting['worker_num']);
                        };
                        $client->onClose = function () use ($client, $address) {
                            if ($client->timer_id) {
                                Timer::clear($client->timer_id);
                                unset($client->timer_id);
                            }
                            Coroutine::sleep(1);
                            Service::debug("reconnect to gateway {$address['lan_host']}:{$address['lan_port']}");
                            $client->connect();
                        };
                        $client->start();
                        $this->gateway_conn_list[$key] = $client;
                    }

                    $off_address_list = array_diff_key($this->gateway_conn_list, $this->gateway_address_list);
                    foreach ($off_address_list as $key => $client) {
                        $client->stop();
                        unset($this->gateway_conn_list[$key]);
                    }
                    break;

                default:
                    break;
            }
        };
        $client->onClose = function (Client $client) {
            Service::debug("closed by register");
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

    private function onGatewayMessage($buffer, $address)
    {
        $data = unpack('Ccmd/Nfd/Nsession_len/A*data', Protocol::decode($buffer));

        $_SESSION = unserialize(substr($data['data'], 0, $data['session_len']));
        $extra = substr($data['data'], $data['session_len']);
        $client = bin2hex(pack('NnN', ip2long($address['lan_host']), $address['lan_port'], $data['fd']));
        switch ($data['cmd']) {
            case Protocol::CLIENT_WEBSOCKET_CONNECT:
                call_user_func([$this->event, 'onWebsocketConnect'], $client, unserialize($extra));
                break;

            case Protocol::CLIENT_CONNECT:
                call_user_func([$this->event, 'onConnect'], $client);
                break;

            case Protocol::CLIENT_MESSAGE:
                call_user_func([$this->event, 'onMessage'], $client, $extra);
                break;

            case Protocol::CLIENT_CLOSE:
                call_user_func([$this->event, 'onClose'], $client, unserialize($extra));
                break;

            default:
                Service::debug("undefined cmd from gateway! cmdcode:{$data['cmd']}");
                break;
        }
    }
}
