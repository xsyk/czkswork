<?php
namespace Swork\Client;

use Swork\Db\Db;
use Swork\Exception\DbException;
use Swork\Pool\DbConnectionInterface;
use Swork\Pool\PoolCollector;
use Swork\Pool\PoolInterface;

class MySql
{
    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * @var PoolInterface
     */
    private $readPool;

    /**
     * 执行语句
     * @param string $sql 待执行的SQL语句
     * @param string $instance 数据库连接容器
     * @param bool $read 是否为使用只读连接池
     * @return array|bool
     * @throws
     */
    public function query(string $sql, string $instance = '', bool $read = false)
    {
        //找到对应的连接池容器
        $this->getCollector($instance);

        //执行语句
        $result = $this->execute($sql, [], [], $read);

        //返回
        return $result['Results'];
    }

    /**
     * 执行SQL语句，并返回结果
     * @param string $sql 待运行的SQL语句
     * @param array $types SQL预处理后需要的字段类型
     * @param array $datas SQL预处理后需要的字段数值
     * @param bool $read 是否为使用只读连接池
     * @return array|bool
     * @throws
     */
    protected function execute(string $sql, array $types = [], array $datas = [], bool $read = false)
    {
        $result = false;

        // 提取连接池（先尝试取到事务连接）
        $transactionStatus = Db::getTransactionStatus();
        $conn = $transactionStatus;
        if ($transactionStatus === false)
        {
            $conn = $this->getConnection($read);
        }
        else if ($transactionStatus === true)
        {
            $conn = $this->getConnection();
            $conn->beginTransaction();
            Db::setTransactionStatus($conn);
        }

        //执行
        try
        {
            $conn->startRecord();
            $result = $conn->query($sql, $types, $datas);

            //回放连接池（非事务状态下才回放到连接池）
            if ($transactionStatus === false)
            {
                $this->releaseConnection($conn, $read);
            }

            //停止记录
            $conn->stopRecord(null);
        }
        catch (\Throwable $ex)
        {
            //停止记录
            $conn->stopRecord($ex);

            //抛出异常
            throw new DbException($ex->getMessage(), $ex->getCode());
        }

        //返回结果
        return [
            'InsertId' => $conn->getInsertId(),
            'AffectedRows' => $conn->getAffectedRows(),
            'Results' => $result,
        ];
    }

    /**
     * @param string $node 数据库节点
     * @throws
     */
    protected function getCollector(string $node)
    {
        //初始化默认连接池（包含读写）
        $this->pool = PoolCollector::getCollector(PoolCollector::MySQL, $node);
        if ($this->pool == false)
        {
            throw new DbException('无法找到连接池容器', 8001);
        }

        //判断是否有只读连接池
        $readOnlyNode = $this->pool->readOnlyNode();
        if ($readOnlyNode != false)
        {
            $this->readPool = PoolCollector::getCollector(PoolCollector::MySQL, $readOnlyNode);
            if ($this->readPool == false)
            {
                throw new DbException('无法找到只读连接池容器', 8002);
            }
        }
    }

    /**
     * 提取连接池（为了转化成DB的接口）
     * @param bool $read 是否为使用只读连接池
     * @return DbConnectionInterface
     */
    protected function getConnection(bool $read = false)
    {
        if ($read && $this->readPool != false)
        {
            return $this->readPool->getConnection();
        }
        return $this->pool->getConnection();
    }

    /**
     * 释放连接
     * @param DbConnectionInterface $conn 连接对象
     * @param bool $read 是否为使用只读连接池
     */
    protected function releaseConnection(DbConnectionInterface $conn, bool $read)
    {
        if ($read && $this->readPool != false)
        {
            $this->readPool->releaseConnection($conn);
        }
        else
        {
            $this->pool->releaseConnection($conn);
        }
    }
}
