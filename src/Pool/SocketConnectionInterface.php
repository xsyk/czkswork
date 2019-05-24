<?php
namespace Swork\Pool;

/**
 * Interface ConnectInterface
 * @package Swoft\Pool
 */
interface SocketConnectionInterface extends ConnectionInterface
{
    /**
     * 发送数据（必须是字符串）
     * @param string $data
     * @return bool
     */
    public function send(string $data);

    /**
     * 接收数据
     * @return string
     */
    public function recv();

    /**
     * 关闭连接
     * @return mixed
     */
    public function close();

    /**
     * 设置在群集模式下使用配置的索引
     * @param int $index
     * @return mixed
     */
    public function setUsedIndex(int $index);
}
