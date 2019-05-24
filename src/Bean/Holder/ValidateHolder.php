<?php
namespace Swork\Bean\Holder;

/**
 * 储存控制器请求数据检验器
 * Class ValidateHolder
 * @package Swork\Bean\Holder
 */
class ValidateHolder
{
    /**
     * @var array
     */
    private static $holder = [];

    /**
     * 复制控制器至容器中
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
     * 根据cls获取对应的校验器
     * @param string $cls 类名
     * @return bool|mixed
     */
    public static function getClass(string $cls)
    {
        return self::$holder[$cls] ?? [];
    }
}
