<?php

declare (strict_types = 1);

namespace Xielei\Swoole;

use Swoole\Server;

class Register extends Service
{
    public $init_file = __DIR__ . '/init/register.php';

    private $host;
    private $port;
    private $secret_key;

    private $gateway_address_list = [];
    private $worker_fd_list = [];

    public function __construct(string $host = '127.0.0.1', int $port = 9327, string $secret_key = '')
    {
        parent::__construct();

        $this->host = $host;
        $this->port = $port;
        $this->secret_key = $secret_key;
    }

    protected function init(Server $server)
    {
        $this->set([
            'worker_num' => 1,
        ]);
        $server->on('WorkerStart', function (...$args) {
            include $this->init_file;
            $this->emit('WorkerStart', ...$args);
        });
        foreach (['WorkerExit', 'WorkerStop', 'Connect', 'Receive', 'Close', 'Packet', 'Task', 'Finish', 'PipeMessage'] as $event) {
            $server->on($event, function (...$args) use ($event) {
                $this->emit($event, ...$args);
            });
        }
        $port = $server->listen($this->host, $this->port, SWOOLE_SOCK_TCP);
        foreach (['Connect', 'Receive', 'Close'] as $event) {
            $port->on($event, function (...$args) use ($event) {
                $this->emit('Port' . $event, ...$args);
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

    private function broadcastGatewayAddressList(int $fd = null)
    {
        $load = pack('C', Protocol::BROADCAST_GATEWAY_ADDRESS_LIST) . implode('', $this->gateway_address_list);
        $buffer = Protocol::encode($load);
        if ($fd) {
            $this->getServer()->send($fd, $buffer);
        } else {
            foreach ($this->worker_fd_list as $fd => $info) {
                $this->getServer()->send($fd, $buffer);
            }
        }

        $addresses = [];
        foreach ($this->gateway_address_list as $fd => $value) {
            $tmp = unpack('Nhost/nport', $value);
            $tmp['host'] = long2ip($tmp['host']);
            $addresses[] = $tmp;
        }
        $addresses = json_encode($addresses);
        Service::debug("broadcastGatewayAddressList fd:{$fd} addresses:{$addresses}");
    }
}
