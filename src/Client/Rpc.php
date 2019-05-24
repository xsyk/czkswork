<?php
namespace Swork\Client;

use Swork\Exception\RpcException;
use Swork\Pool\PoolCollector;
use Swork\Pool\PoolInterface;
use Swork\Pool\Rpc\RpcPool;
use Swork\Pool\SocketConnectionInterface;
use Swork\Server\Rpc\RpcPackage;
use Swork\Server\Rpc\RpcService;
use Swork\Service;

class Rpc
{
    /**
     * @var RpcPool|PoolInterface
     */
    private $pool;

    /**
     * 向RPC服务器发送数据
     * @param string $cmd 数据命令
     * @param mixed $sendData 需要传传输的数据
     * @param string $instance 连接池渠道
     * @return array|bool
     * @throws
     */
    public function send(string $cmd, $sendData, string $instance = '')
    {
        //初始化
        $data = false;
        $target = $sendData['iface'] . '::' . $sendData['name'];

        //找到对应的连接池容器
        $this->getCollector($instance);

        //如果是要求本地运行
        if($this->pool->getIsLocal() && $cmd == 'srv')
        {
            return RpcService::process($sendData);
        }

        //提取连接池
        $conn = $this->getConnection();

        //捕捉异常
        try
        {
            //echo 'rpcSend-' . microtime(true) . PHP_EOL;

            //发送和接收数据
            $rel = $conn->send(RpcPackage::serialize($cmd, $sendData));
            if ($rel == false)
            {
                throw new RpcException("Socket send failed [$target]");
            }

            //取回内容
            $result = $conn->recv();

            //echo 'rpcRecv-' . microtime(true) . PHP_EOL;

            //检查返回
            if ($result == false)
            {
                throw new RpcException("Rpc result empty [$target]");
            }

            //解包
            $info = RpcPackage::unserialize($result);

            //检查数据包
            if (!$info)
            {
                throw new RpcException("Result empty data!");
            }
            if (empty($info['cmd']))
            {
                throw new RpcException("Result miss `cmd`!");
            }
            if (!isset($info['data']) && !is_null($info['data']))
            {
                throw new RpcException("Result miss `data`!");
            }
            if (isset($info['error']))
            {
                $error_msg = $info['error']['msg'] ?? 'unknown error';
                $error_code = $info['error']['code'] ?? 0;
                throw new RpcException($error_msg, $error_code);
            }

            //需返回数据
            $data = $info['data'];
        }
        catch (\Throwable $e)
        {
            Service::$logger->error("Rpc error: {$e->getMessage()}; Tatget: ${target}");
            throw $e;
        }
        finally
        {
            //释放连接池
            $this->pool->releaseConnection($conn);
        }

        //返回
        return $data;
    }

    /**
     * @param string $instance
     * @throws
     */
    private function getCollector(string $instance)
    {
        $this->pool = PoolCollector::getCollector(PoolCollector::Rpc, $instance);
        if ($this->pool == false)
        {
            throw new RpcException('无法找到连接池容器', 8001);
        }
    }

    /**
     * 提取连接池（为了转化接口）
     * @return \Swork\Pool\ConnectionInterface|SocketConnectionInterface
     */
    private function getConnection()
    {
        return $this->pool->getConnection();
    }
}
