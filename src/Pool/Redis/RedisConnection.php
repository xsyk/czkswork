<?php
namespace Swork\Pool\Redis;

use Swork\Exception\RedisException;
use Swork\Pool\AbstractConnection;

/**
 * Interface ConnectInterface
 * @package Swoft\Pool
 */
class RedisConnection extends AbstractConnection
{
    /**
     * 当前连接对象
     * @var \Redis
     */
    private $connection;

    /**
     * Create connectioin
     * @return void
     * @throws
     */
    public function create()
    {
        //获取参数
        $opts = $this->config->getUri();

        //检查是否使用集群的模式
        $cluster = false;
        foreach ($opts as $key => $item)
        {
            if(is_int($key))
            {
                $cluster = true;
            }
            break;
        }

        //判断是使用什么模式
        if($cluster == true)
        {
            $seeds = [];
            foreach ($opts as $key => $item)
            {
                $seeds[] = $item['host'] .':'. $item['port'];
            }
            $this->connection = new \RedisCluster(null, $seeds);
        }
        else
        {
            $host = $opts['host'];
            $port = $opts['port'];

            //协程连接
            $this->connection = new \Redis();
            $connected = $this->connection->connect($host, $port);
            if($connected == false)
            {
                throw new RedisException('Redis connect failed');
            }
        }
    }

    /**
     * 重新连接
     * @throws
     */
    public function reconnect()
    {
        $this->create();
    }

    /**
     * 检查是否连接中
     * @return bool
     */
    public function check(): bool
    {
        return $this->connection->info();
    }

    /**
     * 魔术调用方法
     * @param string $name 指令名
     * @param string $arguments 指令参数
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $rel = false;
        $prefix = $this->config->getPrefix() ?? '';
        $key = $prefix . $arguments[0];
        $params = $arguments[1];

        //重试连接Redis（最多三次）
        for ($tryNum = 0; $tryNum < 3; $tryNum += 1)
        {
            try
            {
                //分不同参数个数调用
                switch (count($params))
                {
                    case 0:
                        $rel = $this->connection->$name($key);
                        break;
                    case 1:
                        $rel = $this->connection->$name($key, $params[0]);
                        break;
                    case 2:
                        $rel = $this->connection->$name($key,$params[0], $params[1]);
                        break;
                    case 3:
                        $rel = $this->connection->$name($key,$params[0], $params[1], $params[3]);
                        break;
                }

                //正常完成
                break;
            }
            catch (\Throwable $exception)
            {
                $this->reconnect();
            }
        }

        //返回
        return $rel;
    }
}
