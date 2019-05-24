<?php
namespace Swork\Bean\Holder;

/**
 * 储存中间件
 * Class MiddlewareHolder
 * @package Swork\Bean\Holder
 */
class MiddlewareHolder
{
    /**
     * @var array
     */
    private static $holder = [];

    /**
     * 复制控制器的路由至容器中
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
     * 根据cls获取对应的中件间
     * @param string $cls 类名
     * @return bool|mixed
     */
    public static function getClass(string $cls)
    {
        return self::$holder[$cls] ?? [];
    }
}
