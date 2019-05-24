<?php
namespace Swork\Bean;

/**
 * 实例化容器
 */
class BeanCollector
{
    /**
     * 初始化
     * BeanCollector constructor.
     * @throws \ReflectionException
     */
    public function __construct()
    {
        Reflection::property($this, $this);
    }
}
