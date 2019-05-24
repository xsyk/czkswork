<?php
namespace Swork\Pool\Rpc;

use Swork\Pool\AbstractConfig;

class RpcConfig extends AbstractConfig
{
    /**
     * 获取是否本地运行
     * @return string
     */
    public function getIsLocal(): string
    {
        return $this->confg['local'] ?? false;
    }
}
