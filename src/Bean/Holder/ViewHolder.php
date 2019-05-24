<?php
namespace Swork\Bean\Holder;

/**
 * 实例化容器池（模板视图）
 * Class ViewHolder
 * @package Swork\Bean\Holder
 */
class ViewHolder
{
    /**
     * @var array
     */
    private static $holder = [];

    /**
     * 根据注解的参数，补全cls类的命令空间
     * @param array $view 所有文件的全路径名和类名
     * @return string
     */
    public static function getClass(array $view)
    {
        //提取参数
        $filename = $view[0];
        $cls = $view[1];

        //判断类是否已经实例化
        if(isset(self::$holder[$cls]))
        {
            return self::$holder[$cls];
        }

        //加载文件
        include_once($filename);

        //实例化
        $inc = new $cls();
        self::$holder[$cls] = $inc;

        //返回
        return $inc;
    }
}
