<?php
namespace Swork\Pool\Rpc;

use Swork\Exception\RpcException;
use Swork\Pool\AbstractPool;
use Swork\Pool\ConnectionInterface;

/**
 * RPC服务连接池
 */
class RpcPool extends AbstractPool
{
    /**
     * 服务器数量
     * @var int
     */
    private $srvCount = 0;

    /**
     * 标识是不是集群模式
     * @var bool
     */
    private $isCluster = false;

    /**
     * 创建连接
     * @param int $type 连接池类型
     * @return ConnectionInterface
     * @throws
     */
    public function createConnection(int $type): ConnectionInterface
    {
        $conn = null;

        //初始化
        $this->getSrvCount();
        $this->checkIsCluster();

        //重试方式连接下一个服务器（最多重复三轮）
        for ($tryNum = 0; $tryNum < 3; $tryNum += 1)
        {
            //是否连接成功
            $isConnected = false;

            //集群逐个尝试连接
            for ($srvIdx = 0; $srvIdx < $this->srvCount; $srvIdx += 1)
            {
                try
                {
                    //创建连接对象
                    $conn = new RpcConnection($this->config);
                    $conn->setUsedIndex($this->isCluster ? $srvIdx : -1);
                    $conn->setType($type);
                    $conn->create();

                    //连接成功
                    $isConnected = true;

                    //跳出
                    break;
                }
                catch (RpcException $exception)
                {
                    //如果最后一次还是失败了，抛出异常
                    if ($tryNum == 2 && $srvIdx == $this->srvCount - 1)
                    {
                        throw $exception;
                    }
                }
            }

            //连接成功，提前跳出循环
            if ($isConnected == true)
            {
                break;
            }
        }

        //返回
        return $conn;
    }

    /**
     *
     * @return bool
     */
    public function getIsLocal()
    {
        return $this->config->getIsLocal();
    }

    /**
     * 检查是否是集群
     */
    private function checkIsCluster()
    {
        foreach ($this->config->getUri() as $key => $value)
        {
            if(is_int($key))
            {
                $this->isCluster = true;
                break;
            }
        }
    }

    /**
     * 获取服务器数量（非集群模式为1）
     */
    private function getSrvCount()
    {
        $uri = $this->config->getUri();
        $this->srvCount = 1;
        if (isset($uri[0]))
        {
            $this->srvCount = count($uri);
        }
    }
}
