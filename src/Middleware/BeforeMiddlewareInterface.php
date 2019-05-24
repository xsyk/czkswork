<?php
namespace Swork\Middleware;

use Swork\Server\Http\Argument;

interface BeforeMiddlewareInterface
{
    /**
     * 中间件处理层（请求前的处理）
     * @param Argument $argument 请求参数
     */
    public function process(Argument $argument);
}
