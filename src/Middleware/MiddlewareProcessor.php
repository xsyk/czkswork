<?php
namespace Swork\Middleware;

use Swork\Bean\Holder\BreakerHolder;
use Swork\Bean\Holder\MiddlewareHolder;
use Swork\Bean\Holder\InstanceHolder;
use Swork\Breaker\BreakerExecutor;
use Swork\Breaker\BreakerProcessor;
use Swork\Server\ArgumentInterface;

class MiddlewareProcessor
{
    /**
     * 执行请求前的中间件
     * @param array $cls 当前控制器类名
     * @param ArgumentInterface $arg 请求参数
     * @throws
     */
    public static function beforeMiddleware(array $cls, ArgumentInterface $arg)
    {
        //中间件统一方法
        $method = 'process';

        //先处理全局的中间件
        $globals = MiddlewareHolder::getClass('global') ?? [];
        foreach ($globals as $item)
        {
            $_cls = InstanceHolder::getClass($item);
            if($_cls instanceof BeforeMiddlewareInterface)
            {
                $_cls->$method($arg);
                $breaker = BreakerHolder::getClass($item)[$method] ?? false;
                if ($breaker != false)
                {
                    BreakerExecutor::process($breaker);
                }
            }
        }

        //提取当前类的中间件
        $middlewares = MiddlewareHolder::getClass($cls[0]);

        //再处理所有Class的中间件
        foreach ($middlewares['class'] ?? [] as $item)
        {
            $_cls = InstanceHolder::getClass($item);
            if($_cls instanceof BeforeMiddlewareInterface)
            {
                $_cls->$method($arg);
            }
        }

        //最后处理Method的中间件
        foreach ($middlewares[$cls[1]] ?? [] as $item)
        {
            $_cls = InstanceHolder::getClass($item);
            if($_cls instanceof BeforeMiddlewareInterface)
            {
                $_cls->$method($arg);
            }
        }
    }

    /**
     * 执行请求后的中间件
     * @param array $cls 当前类名
     * @param ArgumentInterface $arg 请求参数
     * @param mixed $result 逻辑处理后的结果
     * @throws
     */
    public static function afterMiddleware(array $cls, ArgumentInterface $arg, &$result)
    {
        //中间件统一方法
        $method = 'process';

        //提取当前类的中间件
        $middlewares = MiddlewareHolder::getClass($cls[0]);

        //先处理Method的中间件
        foreach ($middlewares[$cls[1]] ?? [] as $item)
        {
            $_cls = InstanceHolder::getClass($item);
            if($_cls instanceof AfterMiddlewareInterface)
            {
                $_cls->$method($arg, $result);
            }
        }

        //先处理所有Class的中间件
        foreach ($middlewares['class'] ?? [] as $item)
        {
            $_cls = InstanceHolder::getClass($item);
            if($_cls instanceof AfterMiddlewareInterface)
            {
                $_cls->$method($arg, $result);
            }
        }

        //最后处理所有Global的中间件
        $globals = MiddlewareHolder::getClass('global') ?? [];
        foreach ($globals as $item)
        {
            $_cls = InstanceHolder::getClass($item);
            if($_cls instanceof AfterMiddlewareInterface)
            {
                $_cls->$method($arg, $result);
            }
        }
    }
}
