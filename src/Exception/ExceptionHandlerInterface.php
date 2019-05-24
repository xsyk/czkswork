<?php
namespace Swork\Exception;

use Swork\Server\Http\Argument;

/**
 * 默认异常接口
 * Interface ExceptionHandlerInterface
 * @package Swork\Exception
 */
interface ExceptionHandlerInterface
{
    /**
     * 处理异常
     * @param Argument $argument 当前请求
     * @param \Throwable $ex 异常内容
     * @return mixed
     */
    public function handler(Argument $argument, \Throwable $ex);
}
