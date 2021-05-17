<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Swoole\ConnectionPool;
use Swoole\Server;
use Swoole\Timer;
use Throwable;
use Xielei\Swoole\Cmd\Ping;
use Xielei\Swoole\Cmd\RegisterWorker;

class Worker extends Server
{
    public $register_host = '127.0.0.1';
    public $register_port = 3327;
    public $register_secret_key = '';

    private $gateway_address_list = [];
    private $gateway_pool_list = [];
    private $gateway_pool_count = 5;

    private $event;

    public function __construct(Event $event)
    {
        $this->event = $event;
        parent::__construct('/var/run/myserv.sock', 0, SWOOLE_PROCESS, SWOOLE_UNIX_STREAM);
    }

    public function start()
    {
        $this->on('Receive', function (Worker $worker, int $fd, int $reactorId, string $data) {
        });

        $this->on('WorkerStart', function (Worker $worker, int $worker_id) {
            Api::$address_list = &$this->gateway_address_list;
            $this->connectToRegister();
            call_user_func([$this->event, 'onWorkerStart'], $worker, $worker_id);
        });

        $this->on('WorkerStop', function (Worker $worker, int $worker_id) {
            call_user_func([$this->event, 'onWorkerStop'], $worker, $worker_id);
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
            $client->send(Protocol::encode(Protocol::WORKER_CONNECT, [
                'register_secret_key' => $this->register_secret_key,
            ]));

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
                echo "Protocol::decode failuer! buffer:{$hex_buffer}\n";
                return;
            }

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
        $client->start();
    }

    private function refreshGatewayPoolList()
    {
        $new_address_list = array_diff_key($this->gateway_address_list, $this->gateway_pool_list);
        foreach ($new_address_list as $key => $address) {
            $this->gateway_pool_list[$key] = new ConnectionPool(function () use ($address) {
                $client = new Client($address['lan_host'], $address['lan_port']);
                $client->onConnect = function () use ($client) {
                    $client->send(pack('NC', 5, RegisterWorker::getCommandCode()));

                    $ping_buffer = pack('NC', 5, Ping::getCommandCode());
                    Timer::tick(30000, function () use ($client, $ping_buffer) {
                        $client->send($ping_buffer);
                    });
                };
                $client->onMessage = function (string $buffer) use ($address) {
                    $this->onGatewayMessage($buffer, $address);
                };
                $client->onClose = function () use ($client) {
                    Timer::after(1000, function () use ($client) {
                        $client->connect();
                    });
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
            echo "Protocol::decode failure! buffer:{$hex}\n";
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
                echo "Undefined CMD! cmdcode:{$data['cmd']}\n";
                break;
        }
    }
}
