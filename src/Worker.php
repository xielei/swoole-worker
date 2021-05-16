<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Swoole\ConnectionPool;
use Swoole\Process\Pool;
use Swoole\Timer;
use Throwable;
use Xielei\Swoole\Cmd\Ping;
use Xielei\Swoole\Cmd\RegisterWorker;

class Worker extends Pool
{
    public $register_host = '127.0.0.1';
    public $register_port = 3327;
    public $register_secret_key = '';

    private $gateway_address_list = [];
    private $gateway_pool_list = [];
    private $gateway_pool_count = 5;

    private $event;

    public function __construct(Event $event, int $worker_num = 1)
    {
        $this->event = $event;
        parent::__construct($worker_num);
    }

    public function start()
    {

        $this->on('WorkerStart', function (Pool $pool, $worker_id) {
            Api::$address_list = &$this->gateway_address_list;
            $this->connectToRegister();
            call_user_func([$this->event, 'onWorkerStart'], $pool, $worker_id);
        });

        $this->on('WorkerStop', function (Pool $pool, $worker_id) {
            call_user_func([$this->event, 'onWorkerStop'], $pool, $worker_id);
        });

        $this->set([
            'enable_coroutine' => true,
        ]);

        parent::start();
    }

    private function connectToRegister()
    {
        $client = new Client($this->register_host, $this->register_port);
        $client->onConnect = function () use ($client) {
            // echo "DEBUG register 连接成功\n";
            $client->send(Protocol::encode(Protocol::WORKER_CONNECT, [
                'register_secret_key' => $this->register_secret_key,
            ]));
            // echo "DEBUG register 注册完成\n";

            $ping_buffer = Protocol::encode(Protocol::PING);
            Timer::tick(30000, function () use ($client, $ping_buffer) {
                $client->send($ping_buffer);
            });
        };
        $client->onMessage = function (string $buffer) {

            try {
                $data = Protocol::decode($buffer);
            } catch (Throwable $th) {
                $hex_buffer = bin2hex($buffer);
                echo "DEBUG 解码失败 buffer:{$hex_buffer}\n";
                return;
            }

            // $str = json_encode($data);
            // echo "DEBUG 收到register消息 解析成功 data:{$str}\n";

            switch ($data['cmd']) {
                case Protocol::BROADCAST_ADDRESS_LIST:
                    $this->gateway_address_list = $data['addresses'];
                    $this->refreshGatewayPoolList();
                    break;

                default:
                    break;
            }
        };
        $client->onClose = function () use ($client) {
            Timer::after(1000, function () use ($client) {
                $client->connect();
            });
        };
        $client->onError = function ($errCode) use ($client) {
            // if ($errCode !== SOCKET_ETIMEDOUT) {
            //     $client->close(true);
            // }
        };
        $client->start();
    }

    private function refreshGatewayPoolList()
    {
        $new_address_list = array_diff_key($this->gateway_address_list, $this->gateway_pool_list);
        foreach ($new_address_list as $key => $address) {
            $this->gateway_pool_list[$key] = new ConnectionPool(function () use ($address) {
                $client = new Client($address['lan_host'], $address['lan_port']);
                $client->onConnect = function () use ($client) {
                    // echo "DEBUG worker 连接成功\n";
                    $client->send(pack('NC', 5, RegisterWorker::getCommandCode()));
                    // echo "DEBUG worker 注册完成\n";

                    $ping_buffer = pack('NC', 5, Ping::getCommandCode());
                    Timer::tick(30000, function () use ($client, $ping_buffer) {
                        $client->send($ping_buffer);
                    });
                };
                $client->onMessage = function (string $buffer) use ($address) {
                    $this->onGatewayMessage($buffer, $address);
                };
                $client->onClose = function () use ($client) {
                    // echo "DEBUG close..9idf\n";
                    Timer::after(1000, function () use ($client) {
                        // echo "DEBUG close..90000\n";
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
                return $client;
            }, $this->gateway_pool_count);
            $this->gateway_pool_list[$key]->fill();
        }

        $off_address_list = array_diff_key($this->gateway_pool_list, $this->gateway_address_list);
        foreach ($off_address_list as $key => $pool) {
            $pool->close();
            unset($this->gateway_pool_list[$key]);
        }
    }

    public static function addressToClient(array $address): string
    {
        return bin2hex(pack('NnN', ip2long($address['lan_host']), $address['lan_port'], $address['fd']));
    }

    public static function clientToAddress(string $client): array
    {
        $res = unpack('Nlan_host/nlan_port/Nfd', hex2bin($client));
        $res['lan_host'] = long2ip($res['lan_host']);
        return $res;
    }

    private function onGatewayMessage($buffer, $address)
    {
        try {
            $data = Protocol::decode($buffer);
        } catch (Throwable $th) {
            $hex = bin2hex($buffer);
            echo "DEBUG 收到gateway消息 解析失败 buffer:{$hex}\n";
            return;
        }

        $_SESSION = $data['session'];
        $client = self::addressToClient([
            'lan_host' => $address['lan_host'],
            'lan_port' => $address['lan_port'],
            'fd' => $data['fd'],
        ]);

        switch ($data['cmd']) {

            case Protocol::CLIENT_WEBSOCKET_CONNECT:
                call_user_func([$this->event, 'onWebsocketConnect'], $client, $data['global']);
                break;

            case Protocol::CLIENT_CONNECT:
                call_user_func([$this->event, 'onConnect'], $client);
                break;

            case Protocol::CLIENT_MESSAGE:
                call_user_func([$this->event, 'onMessage'], $client, $data['message']);
                break;

            case Protocol::CLIENT_CLOSE:
                call_user_func([$this->event, 'onClose'], $client, $data['bind']);
                break;

            default:
                echo "DEBUG 未知执行方法 cmd:{$data['cmd']}\n";
                break;
        }
    }
}
