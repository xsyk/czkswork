<?php
namespace Swork\Db;

use Swoole\Coroutine;
use Swork\Context;
use Swork\Exception\DbException;
use Swork\Pool\DbConnectionInterface;

class Db
{
    /**
     * @var array 事务状态列表
     */
    private static $transactionStatuses = [];

    /**
     * 获取事务状态
     */
    public static function getTransactionStatus()
    {
        $cid = Coroutine::getuid();
        return isset(self::$transactionStatuses[$cid]) ? self::$transactionStatuses[$cid] : false;
    }

    /**
     * 设置事务状态
     * @param mixed 状态 true: 标记开始事务；false：未标记；DbConnectionInterface：连接对象
     */
    public static function setTransactionStatus($status)
    {
        $cid = Coroutine::getuid();
        self::$transactionStatuses[$cid] = $status;
    }

    /**
     * 开始事务
     */
    public static function beginTransaction()
    {
        self::setTransactionStatus(true);
    }

    /**
     * 提交事务
     * @throws
     */
    public static function commit()
    {
        $cid = Coroutine::getuid();
        $conn = self::getTransConnection();
        $conn->commit();
        unset(self::$transactionStatuses[$cid]);
    }

    /**
     * 回滚
     * @throws
     */
    public static function rollback()
    {
        $cid = Coroutine::getuid();
        $conn = self::getTransConnection();
        $conn->rollback();
        unset(self::$transactionStatuses[$cid]);
    }

    /**
     * @return DbConnectionInterface
     * @throws
     */
    private static function getTransConnection()
    {
        $cid = Coroutine::getuid();
        $conn = null;
        if (isset(self::$transactionStatuses[$cid]))
        {
            $conn = self::$transactionStatuses[$cid];
        }
        if (!($conn instanceof DbConnectionInterface))
        {
            throw new DbException('事务连接不存在');
        }
        return $conn;
    }
}
