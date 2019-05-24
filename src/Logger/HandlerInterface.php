<?php
namespace Swork\Logger;

/**
 * Interface LoggerHandler
 * @package Swork\Logger
 */
interface HandlerInterface
{
    /**
     * 刷入日志
     * @param array $data 日志数据
     */
    public function flush(array $data);
}
