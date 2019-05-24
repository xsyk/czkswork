<?php
namespace Swork\Pool\Rpc;

use Swork\Pool\Types;

class RpcDriver
{
    /**
     * 连接服务器
     * @param int $type 1：普通的方式，2：协程的方式
     * @param string $host
     * @param int $port
     * @return bool|\Swoole\Coroutine\Client|\Swoole\Client
     */
    public static function connect(int $type, string $host, int $port)
    {
        $conn = self::getInstance($type);
        $conn->set([
            'open_eof_check' => true,
            'package_eof' => "\r\n\r\n",
            'package_max_length' => 1024 * 1024 * 2, //2M
        ]);
        $connected = $conn->connect($host, $port, 10);

        //判断连接是否成功
        if ($connected == false || !$conn->isConnected())
        {
            return false;
        }

        //返回
        return $conn;
    }

    /**
     * 实例化连接方式
     * @param int $type
     * @return \Swoole\Coroutine\Client|\Swoole\Client
     */
    private static function getInstance(int $type)
    {
        $className = '\Swoole\Coroutine\Client';
        if ($type == Types::Normal)
        {
            $className = '\Swoole\Client';
        }
        return new $className(SWOOLE_SOCK_TCP);
    }
}
