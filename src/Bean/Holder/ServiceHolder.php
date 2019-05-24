<?php
namespace Swork\Bean\Holder;

/**
 * 实例化容器池（实现单件化）
 * Class ServiceHolder
 * @package Swork\Bean\Holder
 */
class ServiceHolder
{
    /**
     * @var array
     */
    private static $holder = [];

    /**
     * 设置容器值
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
     * 根据注解的参数，补全cls类的命令空间
     * @param string $interface 接口名称（全路径）
     * @return string
     */
    public static function getClass(string $interface)
    {
        return self::$holder[$interface] ?? false;
    }
}
