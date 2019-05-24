<?php
namespace Swork\Server\Task;

use Swoole\Coroutine;
use Swork\Cluster\HeartBeat;
use Swork\Helper\ArrayHelper;
use Swork\Pool\Rpc\RpcDriver;
use Swork\Pool\Types;
use Swork\Service;

/**
 * 分布式任务器分配器
 */
class TaskDispatch
{
    /**
     * 心跳对象
     * @var HeartBeat
     */
    private $heartBeat;

    /**
     * 每个服务器的任务线程数
     * @var array
     */
    private $workers;

    /**
     * 上次执行状态的KEY
     * @var string
     */
    private $key;

    /**
     * 分配结果
     * @var array
     */
    private $results = [];

    /**
     * 已经连接上的服务器
     * @var array
     */
    private $servers = [];

    /**
     * 设置心跳对象
     * @param HeartBeat $heartBeat 心跳服务
     */
    function setHeartBead(HeartBeat $heartBeat)
    {
        $this->heartBeat = $heartBeat;
    }

    /**
     * 更新连接配置池与任务分配的状态
     */
    function upgrade()
    {
        //获取最新的KEY
        $stats = [];
        $conns = $this->heartBeat->getConns();
        foreach ($conns as $conn)
        {
            $stats[] = $conn['id'] . '-' . $conn['stat'];
        }
        $key = md5(join(',', $stats));

        //如果KEY不一样，重置分配结果
        if ($key != $this->key)
        {
            //重置所有
            $this->results = [];
            $this->workers = [];

            //重新分配
            foreach ($conns as $conn)
            {
                $id = $conn['id'];
                if ($conn['stat'] != 1)
                {
                    continue;
                }
                $this->results[$id] = 0;
                $this->workers[$id] = $conn['workers'];
            }

            //更换KEY
            $this->key = $key;
        }
    }

    /**
     * 提取一个已发送任务最小量的连接，并返回服务器ID
     * @return string
     */
    function pop()
    {
        //排序
        asort($this->results);

        //提取第一个返回
        foreach ($this->results as $key => $result)
        {
            return $key;
        }

        //返回（不存在）
        return false;
    }

    /**
     * 发送任务至目标服务器这定
     * @param string $id 目标服务器ID
     * @param string $data
     * @return bool
     */
    function send(string $id, string $data)
    {
        //累加分派量
        $this->results[$id] += 1 / ($this->workers[$id] ?? 1);

        //获取远程服务器
        $cli = null;
        $rel = false;
        try
        {
            //获取配置
            $connConf = ArrayHelper::getValues($this->heartBeat->getConns(), $id, 'id');
            $host = $connConf['host'];
            $port = $connConf['port'];

            // Todo 延迟1毫秒，防止莫名重启（暂时这样处理）
            Coroutine::sleep(0.01);

            //连接服务器
            $cli = RpcDriver::connect(Types::Coroutine, $host, $port);
            if (!$cli)
            {
                throw new \Exception("Connect error. host=${host}, port={$port}");
            }

            //发送数据
            $cli->send($data);
            $cli->recv(5);
            $rel = true;
        }
        catch (\Throwable $ex)
        {
            Service::$logger->error('TaskDispatch send error', [$ex->getMessage()]);
        }

        //返回结果
        return $rel;
    }
}
