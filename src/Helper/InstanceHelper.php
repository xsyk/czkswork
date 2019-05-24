<?php
namespace Swork\Helper;

/**
 * 实列化工具
 * Class InstanceHelper
 * @package Swork\Helper
 */
class InstanceHelper
{
    /**
     * 插件实例化
     * @var array
     */
    private static $instance = array();

    /**
     * 获取单件实例
     * @param string $cls 类名
     * @return mixed
     */
    public static function getSingle(string $cls)
    {
        if (!isset(self::$instance[$cls]))
        {
            return self::$instance[$cls] = new $cls();
        }
        return self::$instance[$cls];
    }
}
