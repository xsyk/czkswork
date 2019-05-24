<?php
namespace Swork\Server\Http;

use Swork\Bean\Holder\ControllerHolder;
use Swork\Bean\Holder\InstanceHolder;
use Swork\Bean\Holder\ViewHolder;
use Swork\Exception\HttpException;
use Swork\Exception\MiddlewareException;
use Swork\Middleware\MiddlewareProcessor;
use Swork\Service;

class Request
{
    /**
     * 全局环境变量
     * @var array
     */
    private $env;

    /**
     * 请求地址
     * @var string
     */
    private $uri;

    /**
     * Request constructor.
     * @param array $env
     */
    public function __construct(array $env)
    {
        $this->env = $env;
    }

    /**
     * 设置请求地址
     * @param string $uri
     */
    function setUri(string $uri)
    {
        $this->uri = rtrim($uri, '/');
    }

    /**
     * HTTP处理器
     * @param Argument $argument 参数
     * @return mixed
     * @throws
     */
    function handler(Argument $argument)
    {
        //获取Class
        $cls = ControllerHolder::getClass($this->uri, $params);
        if ($cls == false)
        {
            throw new HttpException('Can not find the router.', 9001);
        }
        if($params != null)
        {
            $argument->setParams($params);
        }

        //实例化
        $ins = InstanceHolder::getClass($cls[0]);
        if ($ins == false)
        {
            throw new HttpException('Can not find the class.', 9002);
        }

        //处理调用前中间件
        MiddlewareProcessor::beforeMiddleware($cls, $argument);

        //调用方法
        $method = $cls[1];

        //处理请求参数检验
        Validator::checkRequest($cls[0], $method, $argument);

        //执行调用
        $result = $ins->$method($argument);

        //如果绑定View，把返回值传进去处理
        if (isset($cls[2]) && $cls[2] != null)
        {
            $view = ViewHolder::getClass($cls[2]);
            $result = $view->render($result);
        }

        //处理调用后中间件
        MiddlewareProcessor::afterMiddleware($cls, $argument, $result);

        //返回
        return $result;
    }
}
