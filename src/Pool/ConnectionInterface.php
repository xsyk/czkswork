<?php
namespace Swork\Pool;

/**
 * 连接池接口
 */
interface ConnectionInterface
{
    /**
     * 创建一个连接
     * @return void
     */
    public function create();

    /**
     * 重连
     */
    public function reconnect();

    /**
     * 检查是否连接着
     * @return bool
     */
    public function check(): bool;

    /**
     * 设置最后使用时间
     * @return mixed
     */
    public function setLastTime();

    /**
     * 获取最后使用时间
     * @return mixed
     */
    public function getLastTime();

    /**
     * 设置连接池类型
     * @param int $type
     * @return mixed
     */
    public function setType(int $type);

    /**
     * 获取连接池类型
     * @return mixed
     */
    public function getType();
}