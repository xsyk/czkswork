<?php
namespace Swork\Server\Rpc;

use Swork\Bean\Holder\InstanceHolder;
use Swork\Exception\RpcException;

class RpcTask
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
        $cls = $data['cls'] ?? false;
        $name = $data['name'] ?? false;
        $args = $data['args'] ?? [];

        //判断是否合法
        if(empty($cls) || empty($name))
        {
            throw new RpcException('missing arguments');
        }

        //获取实例
        $inc = InstanceHolder::getClass($cls);
        if($inc == false)
        {
            throw new RpcException('get instance failed');
        }

        //调用
        return $inc->$name(...$args);
    }
}
