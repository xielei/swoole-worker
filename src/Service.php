<?php

declare (strict_types = 1);

namespace Xielei\Swoole;

use Swoole\Client as SwooleClient;
use Swoole\Process;
use Swoole\Server as SwooleServer;

abstract class Service extends Cli
{
    protected $pid_file;
    protected $log_file;
    protected $inner_socket;
    protected $daemonize = false;
    protected $server;

    public static $debug_mode = false;

    protected $config = [];

    public function __construct()
    {
        parent::__construct();

        if (!defined('SWOOLE_VERSION')) {
            fwrite(STDOUT, $this->getLogo());
            exit("\n\033[1;31mError: Please install Swoole~\033[0m\n\033[1;36m[Swoole](https://www.swoole.com/)\033[0m\n");
        }

        if (!version_compare(SWOOLE_VERSION, '4.5.0', '>=')) {
            fwrite(STDOUT, $this->getLogo());
            exit("\n\033[1;31mError: Swoole >= 4.5.0, current version:" . SWOOLE_VERSION . "\033[0m\n\033[1;36m[Swoole](https://www.swoole.com/)\033[0m\n");
        }

        $file = str_replace('/', '_', array_pop(debug_backtrace())['file']);
        $this->pid_file = __DIR__ . '/../' . $file . '.pid';
        $this->log_file = __DIR__ . '/../' . $file . '.log';
        $this->inner_socket = '/var/run/' . $file . '.sock';

        $this->addCommand('start', 'start [-d]', 'start the service,\'-d\' daemonize mode', function (array $args): int {
            if ($this->isRun()) {
                fwrite(STDOUT, "the service is running, please run restart if you want to restart service.\n");
                return self::PANEL_LISTEN;
            }

            if (isset($args['d'])) {
                $this->daemonize = true;
            } else {
                $this->daemonize = false;
            }

            if ($this->daemonize) {
                fwrite(STDOUT, "the service is running with daemonize\n");
                if (function_exists('pcntl_fork')) {
                    $child_pid = pcntl_fork();
                    if ($child_pid === -1) {
                        fwrite(STDOUT, "pcntl_fork error\n");
                        return self::PANEL_LISTEN;
                    } else if ($child_pid === 0) {
                        $this->startServer();
                        exit();
                    } else {
                        return self::PANEL_LISTEN;
                    }
                } else {
                    fwrite(STDOUT, "please abli function pcntl_fork.\n");
                    $this->startServer();
                }
            } else {
                fwrite(STDOUT, "the service is running\n");
                $this->startServer();
            }
            return self::PANEL_IGNORE;
        });

        $this->addCommand('restart', 'restart [-d]', 'restart the service,\'-d\' daemonize mode', function (array $args): int {
            if ($this->isRun()) {
                $this->execCommand('stop', $args);
            }
            return $this->execCommand('start', $args);
        });

        $this->addCommand('reload', 'reload', 'reload worker and task', function (array $args): int {
            if (!$this->isRun()) {
                fwrite(STDOUT, "the service is not running!\n");
                return self::PANEL_LISTEN;
            }
            $pid = (int) file_get_contents($this->pid_file);
            $sig = SIGUSR1;
            Process::kill($pid, $sig);
            fwrite(STDOUT, "the service reload command sent successfully!\n");
            fwrite(STDOUT, "you can view the results through the log file:\n");
            fwrite(STDOUT, "'{$this->log_file}'.\n");
            return self::PANEL_LISTEN;
        });
        $this->addCommand('status', 'status', 'displays the running status of the service', function (array $args): int {
            if (!$this->isRun()) {
                fwrite(STDOUT, "the service is not running!\n");
                return self::PANEL_LISTEN;
            }
            $client = new SwooleClient(SWOOLE_UNIX_STREAM);
            $client->set(array(
                'open_length_check' => true,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 0,
            ));
            if ($client->connect($this->inner_socket, 0)) {
                $client->send(Protocol::encode(pack('C', Protocol::SERVER_STATUS)));
                $buffer = $client->recv();
                $res = json_decode(Protocol::decode($buffer), true);
                foreach ($res as $key => $value) {
                    fwrite(STDOUT, str_pad((string) $key, 25, '.', STR_PAD_RIGHT) . ' ' . $value . "\n");
                }
            } else {
                fwrite(STDOUT, "connect failed. please try again..\n");
            }
            return self::PANEL_LISTEN;
        });
        $this->addCommand('stop', 'stop [-f]', 'stop the service,\'-f\' force stop', function (array $args): int {
            if (!$this->isRun()) {
                fwrite(STDOUT, "the service is not running!\n");
                return self::PANEL_LISTEN;
            }

            if (!file_exists($this->pid_file)) {
                fwrite(STDOUT, "PID file '{$this->pid_file}' missing, please find the main process PID and kill!\n");
                return self::PANEL_LISTEN;
            }

            $pid = (int) file_get_contents($this->pid_file);
            if (!Process::kill($pid, 0)) {
                fwrite(STDOUT, "process does not exist!\n");
                return self::PANEL_LISTEN;
            }

            if (isset($args['f'])) {
                $sig = SIGKILL;
            } else {
                $sig = SIGTERM;
            }
            Process::kill($pid, $sig);

            fwrite(STDOUT, "the service stopping...\n");

            $time = time();
            while (true) {
                sleep(1);
                if (!Process::kill($pid, 0)) {
                    if (is_file($this->pid_file)) {
                        unlink($this->pid_file);
                        fwrite(STDOUT, "unlink the pid file success.\n");
                    }
                    fwrite(STDOUT, "the service stopped.\n");
                    break;
                } else {
                    if (time() - $time > 5) {
                        fwrite(STDOUT, "stop the service fail, please try again!\n");
                        break;
                    }
                }
            }
            return self::PANEL_LISTEN;
        });
    }

    private function startServer()
    {
        cli_set_process_title(str_replace('/', '_', array_pop(debug_backtrace())['file']));

        // SWOOLE_BASE SWOOLE_PROCESS
        $server = new SwooleServer($this->inner_socket, 0, SWOOLE_PROCESS, SWOOLE_UNIX_STREAM);
        $this->server = $server;
        $this->init($server);

        $server->set(array_merge([
            'pid_file' => $this->pid_file,
            'log_file' => $this->log_file,
            'daemonize' => $this->daemonize,
            'reload_async' => true,
            'max_wait_time' => 60,

            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 0,

            'open_tcp_keepalive' => false,
            // 'tcp_keepidle' => 6,
            // 'tcp_keepinterval' => 1,
            // 'tcp_keepcount' => 10,

            'heartbeat_idle_time' => 60,
            'heartbeat_check_interval' => 3,
        ], $this->config));

        $server->start();
    }

    abstract protected function init(SwooleServer $server);

    public function getServer(): SwooleServer
    {
        return $this->server;
    }

    public function set(array $config = [])
    {
        $this->config = $config;
    }

    public static function debug(string $info)
    {
        if (self::$debug_mode) {
            fwrite(STDOUT, '[' . date(DATE_ISO8601) . ']' . " {$info}\n");
        }
    }

    private function isRun(): bool
    {
        if (file_exists($this->pid_file)) {
            $pid = (int) file_get_contents($this->pid_file);
            if (Process::kill($pid, 0)) {
                return true;
            }
            return false;
        }
        return false;
    }
}
