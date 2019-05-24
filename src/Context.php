<?php
namespace Swork;

use Swoole\Coroutine;

/**
 * 全局上下文
 * Class Context
 * @package Swork
 */
class Context
{
    /**
     * 储存池
     * @var array
     */
    public static $pool = [];

    /**
     * 获取数据
     * @param string $key
     * @return mixed|bool
     */
    public static function get(string $key)
    {
        $cid = Coroutine::getuid();
        if (isset(self::$pool[$cid][$key]))
        {
            return self::$pool[$cid][$key];
        }
        return false;
    }

    /**
     * 保存数据
     * @param string $key
     * @param mixed $item
     * @return bool
     */
    public static function put(string $key, $item)
    {
        $cid = Coroutine::getuid();
        self::$pool[$cid][$key] = $item;
        return true;
    }

    /**
     * 清除数据
     * @param mixed $key
     */
    public static function delete($key = null)
    {
        $cid = Coroutine::getuid();
        if ($key)
        {
            unset(self::$pool[$cid][$key]);
        }
        else
        {
            unset(self::$pool[$cid]);
        }
    }
}
