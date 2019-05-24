<?php
namespace Swork\Pool\Rpc;

use Swork\Exception\RpcException;
use Swork\Pool\AbstractConnection;
use Swork\Pool\SocketConnectionInterface;

/**
 * Interface ConnectInterface
 * @package Swoft\Pool
 */
class RpcConnection extends AbstractConnection implements SocketConnectionInterface
{
    /**
     * 当前连接对象
     * @var \swoole_client
     */
    private $connection;

    /**
     * 在群集模式下使用配置的索引
     * @var int
     */
    private $usedIndex;

    /**
     * Create connectioin
     * @return void
     * @throws
     */
    public function create()
    {
        //获取参数
        $opts = $this->config->getUri();
        if($this->usedIndex > -1)
        {
            $opts = $opts[$this->usedIndex];
        }
        $host = $opts['host'];
        $port = $opts['port'];
        $type = $this->getType();

        //协程连接客户端（非协程环境切成同步客户端）
        $this->connection = RpcDriver::connect($type, $host, $port);

        //判断连接是否成功
        if ($this->connection === false)
        {
            throw new RpcException('TCP connect failed');
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
        return $this->connection->isConnected();
    }

    /**
     * 发送数据
     * @param string $data
     * @return bool
     * @throws
     */
    public function send(string $data)
    {
        if(!$this->connection->isConnected())
        {
            $this->reconnect();
        }
        return $this->connection->send($data);
    }

    /**
     * 接收数据
     * @return mixed
     */
    public function recv()
    {
        $rec = false;
        try
        {
            $rec = $this->connection->recv();
        }
        catch (\Throwable $exception)
        {
            var_dump('---------------44444444444444444-----------');
            var_dump($exception->getCode());
            var_dump($exception->getMessage());
        }
        if($rec == false)
        {
            var_dump('---------------5555555555555555-----------');
        }
        return $rec;
    }

    /**
     * 关闭连接
     * @return mixed|void
     */
    public function close()
    {
        $this->connection->close(true);
    }

    /**
     * 设置在群集模式下使用配置的索引
     * @param int $index
     * @return mixed
     */
    public function setUsedIndex(int $index)
    {
        $this->usedIndex = $index;
    }
}
