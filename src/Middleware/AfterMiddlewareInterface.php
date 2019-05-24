<?php
namespace Swork\Middleware;

use Swork\Server\Http\Argument;

interface AfterMiddlewareInterface
{
    /**
     * 中间件处理层（请求后的处理）
     * @param Argument $argument 请求参数
     * @param mixed $result 逻辑处理后的结果
     */
    public function process(Argument $argument, &$result);
}
