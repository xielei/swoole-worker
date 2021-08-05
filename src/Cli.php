<?php

declare(strict_types=1);

namespace Xielei\Swoole;

use Throwable;

class Cli
{

    const PANEL_IGNORE = 0;
    const PANEL_START = 1;
    const PANEL_LISTEN = 2;
    const PANEL_EXIT = 99;

    private $cmds = [];

    public function __construct()
    {
        if (php_sapi_name() != 'cli') {
            exit("only run in cli~\n");
        }

        $this->addCommand('help', 'help', 'display help', function (array $args): int {
            fwrite(STDOUT, "{$this->getHelp()}\n");
            return self::PANEL_LISTEN;
        });
        $this->addCommand('exit', 'exit', 'exit cmd panel', function (array $args): int {
            return self::PANEL_EXIT;
        });
        $this->addCommand('clear', 'clear', 'clear screen', function (array $args): int {
            return self::PANEL_START;
        });
    }

    final public function start()
    {
        global $argv;
        if (isset($argv[1])) {
            array_shift($argv);
            $arg_str = implode(' ', $argv);
            $parse = $this->parseCmd($arg_str);
            $this->execCommand($parse['cmd'], $parse['args']);
            exit;
        } else {
            fwrite(STDOUT, "\e[2J");
            fwrite(STDOUT, "\e[0;0H");
            fwrite(STDOUT, "{$this->getLogo()}\n\n");
            fwrite(STDOUT, "Press \e[1;33m[Ctrl+C]\e[0m to exit, send \e[1;33m'help'\e[0m to show help.\n");
            $this->listen();
        }
    }

    private function listen()
    {
        fwrite(STDOUT, "> ");
        $input = trim((string) fgets(STDIN));
        if (!$input) {
            return $this->listen();
        }

        $parse = $this->parseCmd($input);

        switch ($this->execCommand($parse['cmd'], $parse['args'])) {

            case self::PANEL_START:
                return $this->start();
                break;

            case self::PANEL_EXIT:
                break;

            case self::PANEL_LISTEN:
                return $this->listen();
                break;

            default:
                break;
        }
    }

    final protected function addCommand(string $cmd, string $usage, string $help, callable $callback)
    {
        $this->cmds[$cmd] = [
            'usage' => $usage,
            'help' => $help,
            'callback' => $callback,
        ];
    }

    final protected function execCommand(string $cmd, array $args = []): int
    {
        try {
            if (!isset($this->cmds[$cmd])) {
                fwrite(STDOUT, "Command \e[1;34m'{$cmd}'\e[0m is not supported, send \e[1;34m'help'\e[0m to view help.\n");
                return self::PANEL_LISTEN;
            }
            return call_user_func($this->cmds[$cmd]['callback'], $args);
        } catch (Throwable $th) {
            $msg = "\e[1;31mFatal error: \e[0m Uncaught exception '" . get_class($th) . "' with message:\n";
            $msg .= "{$th->getMessage()}\n";
            $msg .= "thrown in {$th->getFile()} on line {$th->getLine()}\n";
            $msg .= "Trace:\n{$th->getTraceAsString()}\n";
            fwrite(STDOUT, "{$msg}");
            return self::PANEL_LISTEN;
        }
    }

    final protected function getLogo(): string
    {
        return <<<str
  \e[0;34m_____ \e[0m                   _  \e[0;34m __          __\e[0m        _
 \e[0;34m/ ____|\e[0m                  | |  \e[0;34m\ \        / /\e[0m       | |           \e[1;37m®\e[0m
\e[0;34m| (___\e[0m__      _____   ___ | | __\e[0;34m\ \  /\  / /\e[0m__  _ __| | _____ _ __
 \e[0;34m\___\e[0m \ \ /\ / / _ \ / _ \| |/ _ \e[0;34m\ \/  \/ /\e[0m _ \| '__| |/ / _ \ '__|
\e[0;34m ____)\e[0m \ V  V / (_) | (_) | |  __/\e[0;34m\  /\  /\e[0m (_) | |  |   <  __/ |
\e[0;34m|_____/\e[0m \_/\_/ \___/ \___/|_|\___| \e[0;34m\/  \/\e[0m \___/|_|  |_|\_\___|_|

=================================================
SwooleWorker is a distributed long connection
development framework based on Swoole.

[HomePage] https://swoole.plus
=================================================
str;
    }

    protected function getHelp(): string
    {
        $str = '';
        $str .= "****************************  HELP  ****************************\n";
        $str .= "*\e[0;36m cmd                           description...\e[0m\n";
        foreach ($this->cmds as $cmd => $opt) {
            $str .= '* ' . str_pad($opt['usage'], 30, ' ', STR_PAD_RIGHT) . $opt['help'] . "\n";
        }
        $str .= "****************************************************************";
        return $str;
    }

    private function parseCmd(string $input): array
    {
        $tmp = explode(' ', $input);
        $cmd = [];
        $args = [];
        while (true) {
            if (!$tmp) {
                break;
            }
            $value = (string) array_shift($tmp);

            if (strpos($value, '--') === 0) {
                $key = substr($value, 2);
                $v = [];
                while (true) {
                    if (!$tmp) {
                        break;
                    }
                    $t = (string) array_shift($tmp);
                    if (strpos($t, '-') === 0) {
                        array_unshift($tmp, $t);
                        break;
                    }
                    $v[] = $t;
                }
                $args[$key] = implode(' ', $v);
            } elseif (strpos($value, '-') === 0) {
                $keys = str_split(str_replace('-', '', $value));
                $v = [];
                while (true) {
                    if (!$tmp) {
                        break;
                    }
                    $t = (string) array_shift($tmp);
                    if (strpos($t, '-') === 0) {
                        array_unshift($tmp, $t);
                        break;
                    }
                    $v[] = $t;
                }
                foreach ($keys as $key) {
                    $args[$key] = implode(' ', $v);
                }
            } else {
                if (!in_array($value, [''])) {
                    $cmd[] = $value;
                }
            }
        }
        return [
            'cmd' => implode(' ', $cmd),
            'args' => $args,
        ];
    }
}
