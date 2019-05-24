<?php
namespace Swork\Pool;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * 连接池管理器
 */
abstract class AbstractPool implements PoolInterface
{
    /**
     * 队列
     * @var \SplQueue
     */
    protected $queue;

    /**
     * 协程管道
     * @var Channel
     */
    protected $channel;

    /**
     * 配置对象
     * @var ConfigInterface
     */
    protected $config;

    /**
     * 初始化连接池
     * MySqlPool constructor
     * @param ConfigInterface $config MySQL配置参数
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;

        //判断是哪种类型（协程或普通的）
        if(Coroutine::getuid() > 0)
        {
            $this->channel = new Channel($this->config->getPools());
        }
        else
        {
            $this->queue = new \SplQueue();
        }
    }

    /**
     * 获取一个连接
     * @return ConnectionInterface
     * @throws
     */
    public function getConnection(): ConnectionInterface
    {
        if(Coroutine::getuid() > 0)
        {
            return $this->getConnectionByCoroutine();
        }
        else
        {
            return $this->getConnectionByNormal();
        }
    }

    /**
     * 使用普通的连接池
     * @return mixed|ConnectionInterface
     */
    private function getConnectionByNormal()
    {
        if($this->queue == null)
        {
            $this->queue = new \SplQueue();
        }

        //如果还有可用的线程
        if ($this->queue->count() > 0)
        {
            return $this->queue->dequeue();
        }

        //新增连接返回
        return $this->createConnection(Types::Normal);
    }

    /**
     * 使用协程连接池
     * @return mixed|ConnectionInterface
     */
    private function getConnectionByCoroutine()
    {
        if($this->channel == null)
        {
            $this->channel = new Channel($this->config->getPools());
        }

        //如果还有可用的线程
        $stats = $this->channel->stats();
        if ($stats['queue_num'] > 0)
        {
            return $this->channel->pop();
        }

        //检查有没有等等的（超10）
        if($stats['consumer_num'] < 10)
        {
            return $this->createConnection(Types::Coroutine);
        }

        //从队列中等待获取回放的
        $writes = [];
        $reads = [$this->channel];
        $result = $this->channel->select($reads, $writes, 0.1);
        if($result != false && !empty($reads))
        {
            return $reads[0]->pop();
        }

        //新增连接返回
        return $this->createConnection(Types::Coroutine);
    }

    /**
     * 释放一个连接
     * @param ConnectionInterface $conn
     */
    public function releaseConnection(ConnectionInterface $conn)
    {
        //更新最后使用时间
        $conn->setLastTime();

        //根据不同的类型进行回放
        if($conn->getType() == Types::Coroutine)
        {
            $this->channel->push($conn);
        }
        else
        {
            $this->queue->enqueue($conn);
        }
    }

    /**
     * 只读节点配置名称
     * @return string
     */
    public function readOnlyNode()
    {
        return $this->config->getRead();
    }

    /**
     * 定时清除长时间没有使用的连接
     */
    public function clearConnection()
    {

    }
}
