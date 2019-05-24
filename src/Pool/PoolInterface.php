<?php
namespace Swork\Pool;

/**
 * Interface PoolInterface
 */
interface PoolInterface
{
    /**
     * 创建连接
     * @param int $type 连接池类型
     * @return ConnectionInterface
     */
    public function createConnection(int $type): ConnectionInterface;

    /**
     * 获取一个连接
     * @return ConnectionInterface
     */
    public function getConnection() : ConnectionInterface;

    /**
     * 释放一个连接
     * @param ConnectionInterface $connection
     */
    public function releaseConnection(ConnectionInterface $connection);

    /**
     * 只读节点配置名称
     * @return string
     */
    public function readOnlyNode();
}
