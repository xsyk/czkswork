<?php
namespace Swork\Pool;

/**
 * 连接池容器
 */
class PoolCollector
{
    /**
     * 连接池类型
     */
    const MySQL = 1;
    const Redis = 2;
    const Rpc = 3;

    /**
     * 连接容器
     * @var array
     */
    private static $connects = [];

    /**
     * 收集一个连接池（储存不同类型的连接池）
     * @param int $type
     * @param string $node 数据库节点
     * @param PoolInterface $pool
     */
    public static function collect(int $type, string $node, PoolInterface $pool)
    {
        if ($node == '')
        {
            $node = 'default';
        }
        self::$connects[$type][$node] = $pool;
    }

    /**
     * 获取一个连接池
     * @param int $type
     * @param string $node 数据库节点
     * @return PoolInterface | bool
     */
    public static function getCollector(int $type, string $node)
    {
        if ($node == '')
        {
            $node = 'default';
        }
        return self::$connects[$type][$node] ?? false;
    }
}
