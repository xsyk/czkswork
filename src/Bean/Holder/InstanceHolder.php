<?php
namespace Swork\Bean\Holder;

/**
 * 实例化容器池（实现单件化）
 * Class InstanceHolder
 * @package Swork\Bean\Holder
 */
class InstanceHolder
{
    /**
     * @var array
     */
    private static $holder = [];

    /**
     * 根据URL获取类
     * @param string $cls 类名（全路径）
     * @param string $diff 相同类不同的实例化对象区分（用于设置不同的参数值）
     * @return bool|mixed
     * @throws
     */
    public static function getClass(string $cls, string $diff = '')
    {
        if(strpos($cls, '\\') !== 0 && strpos($cls, ':') === false)
        {
            $cls = "\\$cls";
        }
        $key = $diff . $cls;
        $inc = self::$holder[$key] ?? false;
        if($inc == false)
        {
            $inc = new $cls();
            self::$holder[$key] = $inc;
        }
        return $inc;
    }

    /**
     * 设置Class实例对象
     * @param string $key 储存KEY
     * @param $inc
     */
    public static function setClass(string $key, $inc)
    {
        self::$holder[$key] = $inc;
    }

    /**
     * 判断是否存在
     * @param string $key 储存KEY
     * @return bool
     */
    public static function isExist(string $key)
    {
        return isset(self::$holder[$key]);
    }
}
