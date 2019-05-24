<?php
namespace Swork\Helper;

class PathHelper
{
    /**
     * DIRECTORY_SEPARATOR
     * @var string
     */
    public static $SEP = DIRECTORY_SEPARATOR;

    /**
     * 路径连接帮助类
     * @param array $paths
     * @return string
     */
    public static function join(...$paths)
    {
        //返回
        return join(self::$SEP, $paths);
    }
}