<?php
namespace Swork\Server\Rpc;

use Swork\Bean\Holder\InstanceHolder;
use Swork\Bean\Holder\ServiceHolder;
use Swork\Exception\RpcException;

class RpcService
{
    /**
     * 处理服务
     * @param array $data
     * @return string
     * @throws
     */
    public static function process(array $data)
    {
        //提取参数
        $iface = $data['iface'] ?? false;
        $name = $data['name'] ?? false;
        $args = $data['args'] ?? false;

        //判断是否合法
        if(empty($iface) || empty($name))
        {
            throw new RpcException('missing arguments');
        }

        //提取运行类
        $cls = ServiceHolder::getClass($iface);
        if($cls == false)
        {
            throw new RpcException("missing iface [$iface]");
        }

        //获取实例
        $inc = InstanceHolder::getClass($cls);
        if($inc == false)
        {
            throw new RpcException("get instance failed [$iface]");
        }

        //调用
        return $inc->$name(...$args);
    }
}
