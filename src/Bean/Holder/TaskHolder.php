<?php
namespace Swork\Bean\Holder;

/**
 * 任务池
 * Class TaskHolder
 * @package Swork\Bean\Holder
 */
class TaskHolder
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
     * 根据组名获取子任务
     * @param string $group 组名
     * @return array|null
     */
    public static function getSalve(string $group)
    {
        if (!empty($group) || isset(self::$holder['salve'][$group]))
        {
            return self::$holder['salve'][$group];
        }
        return null;
    }
}
