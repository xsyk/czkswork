<?php
namespace Swork\Pool;

/**
 * 连接池管理器
 */
abstract class AbstractConnection implements ConnectionInterface
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * 最后使用时间
     * @var int
     */
    private $lastTime;

    /**
     * 连接池类型
     * @var Types
     */
    private $type;

    /**
     * 初始化连接
     * MySqlPool constructor
     * @param ConfigInterface $config 配置参数
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
        $this->lastTime = time();
    }

    /**
     * 设置最后使用的时间
     */
    public function setLastTime()
    {
        $this->lastTime = time();
    }

    /**
     * 获取最后使用的时间
     * @return int|mixed
     */
    public function getLastTime()
    {
        return $this->lastTime;
    }

    /**
     * 设置连接池类型
     * @param int $type
     * @return mixed
     */
    public function setType(int $type)
    {
        $this->type = $type;
    }

    /**
     * 获取连接池类型
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }
}
