<?php
namespace Swork\Pool;

/**
 * Interface ConnectInterface
 * @package Swoft\Pool
 */
interface DbConnectionInterface extends ConnectionInterface
{
    /**
     * 执行SQL语句，并返回结果
     * @param string $sql 待运行的SQL语句
     * @param array $types SQL预处理后需要的字段类型
     * @param array $datas SQL预处理后需要的字段数值
     * @return array|bool
     * @throws
     */
    public function query(string $sql, array $types = [], array $datas = []);

    /**
     * @return mixed
     */
    public function getInsertId();

    /**
     * @return int
     */
    public function getAffectedRows(): int;

    /**
     * Begin transaction
     */
    public function beginTransaction();

    /**
     * Rollback transaction
     */
    public function rollback();

    /**
     * Commit transaction
     */
    public function commit();

    /**
     * 切换数据库
     * @param string $dbName 数据库名
     * @return mixed
     * @throws
     */
    public function useDB($dbName);

    /**
     * 开始记录
     * @return mixed
     */
    public function startRecord();

    /**
     * 停止记录
     * @param \Throwable|null $throwable 异常信息
     * @return mixed
     */
    public function stopRecord($throwable);
}
