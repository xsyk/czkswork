<?php
namespace Swork\Logger;

use Swork\Service;

/**
 * Class Writer
 * @package Swork\Logger
 */
class Writer
{
    /**
     * 处理器
     * @var HandlerInterface
     */
    private $handler = null;

    /**
     * 初始化
     * Writer constructor.
     */
    public function __construct()
    {
        //提取参数
        $conf = Service::$logger->getConf();
        $handler = $conf['handler'] ?? '';
        if ($handler == '')
        {
            $handler = FileHandler::class;
        }

        //初始化Handler
        $this->handler = new $handler();
    }

    /**
     * 通过任务入口，把日志刷入指定的接口之下
     * @param array $data 日志数据
     */
    public function flush(array $data)
    {
        $this->handler->flush($data);
    }
}