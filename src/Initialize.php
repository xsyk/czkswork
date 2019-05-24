<?php
namespace Swork;

use Swork\Pool\MySql\MySqlConfig;
use Swork\Pool\MySql\MySqlPool;
use Swork\Pool\PoolCollector;
use Swork\Pool\Redis\RedisConfig;
use Swork\Pool\Redis\RedisPool;
use Swork\Pool\Rpc\RpcConfig;
use Swork\Pool\Rpc\RpcPool;

class Initialize
{
    /**
     * 初始化连接池容器
     */
    function db()
    {
        foreach (Configer::get('db') as $key => $value)
        {
            $config = new MySqlConfig($value, $key);
            $pool = new MySqlPool($config);
            PoolCollector::collect(PoolCollector::MySQL, $key, $pool);
        }
    }

    /**
     * 初始化Redis连接池
     */
    function redis()
    {
        $instance = 'default';
        $config = new RedisConfig(Configer::get('redis'), $instance);
        $pool = new RedisPool($config);
        PoolCollector::collect(PoolCollector::Redis, $instance, $pool);
    }

    /**
     * 初始化Rpc连接池
     */
    function rpc()
    {
        foreach (Configer::get('rpc') as $key => $value)
        {
            $config = new RpcConfig($value, $key);
            $pool = new RpcPool($config);
            PoolCollector::collect(PoolCollector::Rpc, $key, $pool);
        }
    }
}
