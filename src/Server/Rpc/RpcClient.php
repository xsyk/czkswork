<?php
namespace Swork\Server\Rpc;

use Swork\Client\Rpc;

class RpcClient
{
    /**
     * 使用哪个实例
     * @var string
     */
    private $instance;

    /**
     * 使用哪个接口
     * @var string
     */
    private $interface;

    /**
     * RPC客户端
     * @var Rpc
     */
    private $rpc;

    /**
     * RpcClient constructor.
     */
    public function __construct()
    {
        $this->rpc = new Rpc();
    }

    /**
     * 设置实例名称
     * @param string $name
     */
    public function setInstance(string $name)
    {
        $this->instance = $name;
    }

    /**
     * 设置接口名称
     * @param string $name
     */
    public function setInterface(string $name)
    {
        $this->interface = $name;
    }

    /**
     * 动态调用方法
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws
     */
    public function __call($name, $arguments)
    {
        //组装数据
        $info = [
            'iface' => $this->interface,
            'name' => $name,
            'args' => $arguments,
        ];

        //通过RPC发送数据
        return $this->rpc->send('srv', $info, $this->instance);
    }
}
