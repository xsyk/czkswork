<?php
namespace Swork\Bean\Holder;

/**
 * 储存熔断器
 * Class BreakerHolder
 * @package Swork\Bean\Holder
 */
class BreakerHolder
{
    /**
     * @var array
     */
    private static $holder = [];

    /**
     * 复制熔断器的路由至容器中
     * @param array $holder
     */
    public static function setHolder(array $holder)
    {
        foreach ($holder as $cls => $item)
        {
            self::$holder[$cls] = $item;
        }
    }

    /**
     * 根据cls获取对应的熔断器
     * @param string $cls 类名
     * @return bool|mixed
     */
    public static function getClass(string $cls)
    {
        return self::$holder[$cls] ?? [];
    }
}
