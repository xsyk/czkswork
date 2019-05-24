<?php
namespace Swork\Pool;

/**
 * 连接池配置接口
 */
interface ConfigInterface
{
    /**
     * @return array
     */
    public function getUri(): array ;

    /**
     * @return string
     */
    public function getRead(): string;

    /**
     * @return int
     */
    public function getPools(): int;
}
