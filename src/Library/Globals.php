<?php

declare(strict_types=1);

namespace Xielei\Swoole\Library;

use Swoole\Coroutine\Server\Connection;

class Globals extends SockServer
{
    protected $data = [];

    public function __construct()
    {
        parent::__construct(function (Connection $conn, $params) {
            if (!is_array($params)) {
                return;
            }
            switch (array_shift($params)) {
                case 'get':
                    list($name) = $params;
                    self::sendToConn($conn, $this->getValue($this->data, explode('.', $name)));
                    break;

                case 'set':
                    list($name, $value) = $params;
                    $this->setValue($this->data, explode('.', $name), $value);
                    self::sendToConn($conn, true);
                    break;

                case 'unset':
                    list($name) = $params;
                    self::sendToConn($conn, $this->unsetValue($this->data, explode('.', $name)));
                    break;

                case 'isset':
                    list($name) = $params;
                    self::sendToConn($conn, $this->issetValue($this->data, explode('.', $name)));
                    break;

                default:
                    self::sendToConn($conn, 'no cmd..');
                    break;
            }
        });
    }

    public function get(string $name, $default = null)
    {
        $res = $this->sendAndReceive(['get', $name]);
        return is_null($res) ? $default : $res;
    }

    public function set(string $name, $value): bool
    {
        return $this->sendAndReceive(['set', $name, $value]);
    }

    public function unset(string $name)
    {
        return $this->sendAndReceive(['unset', $name]);
    }

    public function isset(string $name): bool
    {
        return $this->sendAndReceive(['isset', $name]);
    }

    private function issetValue(array $data, array $path): bool
    {
        $key = array_shift($path);
        if (!$path) {
            return isset($data[$key]) ? true : false;
        } else {
            if (isset($data[$key])) {
                return $this->issetValue($data[$key], $path);
            } else {
                return false;
            }
        }
    }

    private function unsetValue(array &$data, array $path): bool
    {
        $key = array_shift($path);
        if (!$path) {
            unset($data[$key]);
            return true;
        } else {
            if (isset($data[$key])) {
                return $this->unsetValue($data[$key], $path);
            } else {
                return true;
            }
        }
    }

    private function getValue(array $data, array $path, $default = null)
    {
        $key = array_shift($path);
        if (!$path) {
            return isset($data[$key]) ? $data[$key] : $default;
        } else {
            if (isset($data[$key])) {
                return $this->getValue($data[$key], $path, $default);
            } else {
                return $default;
            }
        }
    }

    private function setValue(array &$data, array $path, $value)
    {
        $key = array_shift($path);
        if ($path) {
            if (!isset($data[$key])) {
                $data[$key] = [];
            }
            $this->setValue($data[$key], $path, $value);
        } else {
            $data[$key] = $value;
        }
    }
}
