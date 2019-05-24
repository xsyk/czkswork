<?php
namespace Swork\Bean\Holder;

/**
 * 储存路由地址与相应类的关系
 * Class ControllerHolder
 * @package Swork\Bean\Holder
 */
class ControllerHolder
{
    /**
     * @var array
     */
    private static $holder = [];
    private static $regex = [];

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
     * 复制正则至容器中
     * @param array $regex
     */
    public static function setRegex(array $regex)
    {
        self::$regex = $regex;
    }

    /**
     * 根据URL获取类
     * @todo 如果实现地址上参数化？
     * @param string $uri
     * @param mixed $params
     * @return bool|mixed
     */
    public static function getClass(string $uri, &$params = null)
    {
        //默认提取
        $cls = self::$holder[$uri] ?? false;
        if($cls != false)
        {
            return $cls;
        }

        //判断是否满足用户简易路径
        foreach (self::$regex as $regex)
        {
            if(preg_match($regex['regex'], $uri, $match))
            {
                foreach ($regex['param'] as $param)
                {
                    $params[$param] = $match[$param];
                }
                return $regex['target'];
            }
        }

        //补充后线
        if(substr($uri, -1, 1) != '/')
        {
            $uri .= '/';
        }
        $uri .= 'index';

        //再一次提取
        $cls = self::$holder[$uri] ?? false;
        if($cls != false)
        {
            return $cls;
        }

        //没有找到
        return false;
    }
}
