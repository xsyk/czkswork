<?php
namespace Swork\Logger;

/**
 * Class Logger
 * @package Swork\Logger
 */
class Levels
{
    /**
     * 日志级别
     */
    const INFO = 1;
    const ALTER = 2;
    const ERROR = 3;
    const LEVELS = ['INFO', 'ALTER', 'ERROR'];

    /**
     * 获取级别名称
     * @param int $level
     * @return mixed|string
     */
    public static function get(int $level)
    {
        return self::LEVELS[$level - 1] ?? 'NONE';
    }
}
