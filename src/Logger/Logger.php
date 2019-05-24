<?php
namespace Swork\Logger;

use Swork\Configer;
use Swork\Service;

/**
 * Class Logger
 * @package Swork\Logger
 */
class Logger
{
    /**
     * 全局配置
     * @var array
     */
    private $env;

    /**
     * 日志配置
     * @var array
     */
    private $conf;

    /**
     * SHELL输出的颜色
     * @var array
     */
    static $colors = [32, 33, 31];

    /**
     * 实例化
     * @param array $env
     */
    public function __construct(array $env)
    {
        $this->env = $env;
        $this->conf = Configer::get('logger');
    }

    /**
     * 写入日志
     * @param int $level 日志级别 （1：info，2：alert，3：error）
     * @param string $message
     * @param array $context
     * @return mixed
     */
    private function write(int $level, string $message, array $context = array())
    {
        //判断输出日志级别（没有达到级别，则不输出）
        if ($level < ($this->conf['level'] ?? 0))
        {
            return;
        }

        //组装数据
        $data = [
            'time' => round(microtime(true), 3),
            'level' => $level,
            'message' => $message,
            'context' => $context
        ];

        //如果不是后台模式
        if (!$this->env['deamon'])
        {
            $name = $this->conf['name'] ?? 'logger';
            $color = (self::$colors[$level-1] ?? 34) .'m';
            echo "\e[$color" . "$name: ". Formatter::convert($data) ."\e[0m". PHP_EOL;
        }

        //投递至写入任务
        $info = [
            'cls' => Writer::class,
            'name' => 'flush',
            'args' => [$data]
        ];

        //投递任务
        if(Service::$server->taskworker || Service::$server->worker_id != 0)
        {
            $data = [
                'act' => 'RunTask',
                'args' => serialize($info)
            ];
            Service::$server->sendMessage(serialize($data), 0);
        }
        else
        {
            Service::$server->task(serialize($info));
        }
    }

    /**
     * 获取日志配置
     * @return array
     */
    public function getConf()
    {
        return $this->conf;
    }

    /**
     * 信息日志
     * @param string $message
     * @param array $context
     * @return mixed
     */
    public function info(string $message, array $context = array())
    {
        $this->write(Levels::INFO, $message, $context);
    }

    /**
     * 警告日志
     * @param string $message
     * @param array $context
     * @return mixed
     */
    public function alert(string $message, array $context = array())
    {
        $this->write(Levels::ALTER, $message, $context);
    }

    /**
     * 错误日志
     * @param string $message
     * @param array $context
     * @return mixed
     */
    public function error(string $message, array $context = array())
    {
        $this->write(Levels::ERROR, $message, $context);
    }
}
