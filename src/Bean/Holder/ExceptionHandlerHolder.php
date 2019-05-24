<?php
namespace Swork\Bean\Holder;

use Swork\Exception\ExceptionHandlerInterface;

/**
 * 储存异常处理器
 * Class ExceptionHandlerHolder
 * @package Swork\Bean\Holder
 */
class ExceptionHandlerHolder
{
    /**
     * @var array
     */
    private static $holder = [];

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
     * 根据cls获取对应的实例名
     * @param \Throwable $throwable 异常对象
     * @return bool|mixed
     * @throws
     */
    public static function getClass(\Throwable $throwable) : ExceptionHandlerInterface
    {
        $names = explode('\\', get_class($throwable));
        $cls = self::$holder[$names[count($names)-1]] ?? false;
        if($cls == false)
        {
            $cls = self::getDefaultHandler();
        }
        if($cls == false && count(self::$holder) > 0)
        {
            $cls = self::getFirstHandler();
        }
        if($cls == false)
        {
            throw new \Exception('There is no Exception Handler defined!', 9999);
        }
        return InstanceHolder::getClass($cls);
    }

    /**
     * 获取默认处理器
     * @return bool|int|string
     */
    private static function getDefaultHandler()
    {
        return self::$holder['DefaultException'] ?? false;
    }

    /**
     * 获取第一个处理器
     * @return bool|int|string
     */
    private static function getFirstHandler()
    {
        foreach (self::$holder as $cls => $item)
        {
            return $item;
        }
        return false;
    }
}
