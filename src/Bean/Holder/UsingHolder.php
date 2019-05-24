<?php
namespace Swork\Bean\Holder;

/**
 * 实例化容器池（实现单件化）
 * Class InstanceHolder
 * @package Swork\Bean\Holder
 */
class UsingHolder
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
     * @param string $cls 所有文件的类名
     * @param string $arg 需要补全的参数（输出补全的类）
     * @return string
     */
    public static function getClass(string $cls, string $arg)
    {
        $pos = strpos($arg, ':');
        if ($pos !== false)
        {
            $arg = substr($arg, 0, $pos);
        }
        if (substr($arg, 0, 1) != '\\')
        {
            $list = self::$holder[$cls] ?? [];
            $ns = $list[$arg] ?? false;
            if ($ns == false)
            {
                $ns = $list['#'] . '\\' . $arg;
            }
            $arg = $ns;
        }
        return $arg;
    }
}
