<?php
namespace Swork\Pool;

abstract class AbstractConfig implements ConfigInterface
{
    /**
     * 配置
     * @var array
     */
    protected $confg;

    /**
     * 实例名
     * @var
     */
    protected $instance;

    /**
     * MySqlConfig constructor.
     * @param array $config MySQL配置内容
     * @param string $instance 实例名
     */
    public function __construct(array $config, string $instance)
    {
        $this->confg = $config;
        $this->instance = $instance;
    }

    /**
     * @return array
     */
    public function getUri(): array
    {
        return $this->confg['uri'];
    }

    /**
     * @return string
     */
    public function getRead(): string
    {
        return $this->confg['read'] ?? false;
    }

    /**
     * @return int
     */
    public function getPools(): int
    {
        return $this->confg['pools'] ?? 50;
    }
}
