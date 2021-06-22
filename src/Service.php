<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Swoole\Process;
use Swoole\Coroutine;
use Swoole\Server as SwooleServer;
use Xielei\Swoole\Library\Globals;

define('SW_VERSION', '1.0.4');

/**
 * @property Globals $globals
 */
abstract class Service extends Cli
{
    protected $pid_file;
    protected $daemonize = false;

    protected $server;
    protected $globals;

    protected $events;

    protected $reload_file;

    public static $debug_mode = false;

    protected $config = [];

    public $auto_reload = false;
    public $auto_reload_dir = [];
    public $auto_reload_interval = 5;

    public function __construct()
    {
        parent::__construct();

        if (!defined('SWOOLE_VERSION')) {
            fwrite(STDOUT, $this->getLogo());
            exit("\n\033[1;31mError: Please install Swoole~\033[0m\n\033[1;36m[Swoole](https://www.swoole.com/)\033[0m\n");
        }

        if (!version_compare(SWOOLE_VERSION, '4.6.0', '>=')) {
            fwrite(STDOUT, $this->getLogo());
            exit("\n\033[1;31mError: Swoole >= 4.6.0, current version:" . SWOOLE_VERSION . "\033[0m\n\033[1;36m[Swoole](https://www.swoole.com/)\033[0m\n");
        }

        $this->pid_file = __DIR__ . '/../' . str_replace('/', '_', array_pop(debug_backtrace())['file']) . '.pid';

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
            fwrite(STDOUT, "you can view the results through the log file.\n");
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

    private function getFilesTime($path, &$files)
    {
        if (is_dir($path)) {
            $dp = dir($path);
            while ($file = $dp->read()) {
                if ($file !== "." && $file !== "..") {
                    $this->getFilesTime($path . "/" . $file, $files);
                }
            }
            $dp->close();
        }
        if (is_file($path)) {
            $files[$path] = filemtime($path);
        }
    }

    private function startServer()
    {
        cli_set_process_title(str_replace('/', '_', array_pop(debug_backtrace())['file']));
        $server = $this->createServer();
        $this->globals = new Globals();
        $this->globals->mountTo($server);
        if ($this->auto_reload) {
            $server->addProcess(new Process(function () use ($server) {
                $filetimes = [];
                foreach ($this->auto_reload_dir as $dir) {
                    $this->getFilesTime($dir, $filetimes);
                }
                while (true) {
                    clearstatcache();
                    $tmp_filetimes = [];
                    foreach ($this->auto_reload_dir as $dir) {
                        $this->getFilesTime($dir, $tmp_filetimes);
                    }
                    if ($tmp_filetimes != $filetimes) {
                        $filetimes = $tmp_filetimes;
                        $server->reload();
                    }
                    Coroutine::sleep($this->auto_reload_interval);
                }
            }, false, 2, true));
        }

        $server->on('WorkerStart', function (...$args) {
            if ($this->reload_file) {
                include $this->reload_file;
            }
            $this->emit('WorkerStart', ...$args);
        });

        foreach (['WorkerExit', 'WorkerStop', 'PipeMessage', 'Task', 'Finish', 'Connect', 'Receive', 'Close', 'Open', 'Message', 'Request', 'Packet'] as $event) {
            $server->on($event, function (...$args) use ($event) {
                $this->emit($event, ...$args);
            });
        }

        $server->set(array_merge($this->config, [
            'pid_file' => $this->pid_file,
            'daemonize' => $this->daemonize,
            'event_object' => true,
            'task_object' => true,

            'reload_async' => true,
            'max_wait_time' => 60,

            'enable_coroutine' => true,
            'task_enable_coroutine' => true,
        ]));
        $this->server = $server;
        $server->start();
    }

    abstract protected function createServer(): SwooleServer;

    public function set(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    protected function emit(string $event, ...$args)
    {
        $event = strtolower('on' . $event);
        Service::debug("emit {$event}");
        call_user_func($this->events[$event] ?: function () {
        }, ...$args);
    }

    protected function on(string $event, callable $callback)
    {
        $event = strtolower('on' . $event);
        $this->events[$event] = $callback;
    }

    public function getServer(): SwooleServer
    {
        return $this->server;
    }
    
    public function getGlobals(): Globals
    {
        return $this->globals;
    }

    public static function debug(string $info)
    {
        if (self::$debug_mode) {
            fwrite(STDOUT, '[' . date(DATE_ISO8601) . ']' . " {$info}\n");
        }
    }

    protected function isRun(): bool
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
