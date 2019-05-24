<?php
namespace Swork\Pool\Redis;

use Swork\Pool\AbstractConfig;

class RedisConfig extends AbstractConfig
{
    /**
     * 获取储存KEY的前缀
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->confg['prefix'];
    }
}
