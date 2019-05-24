<?php
namespace Swork\Logger;

/**
 * Class FileHandler
 * @package Swork\Logger
 */
class FileHandler extends AbstractHandler implements HandlerInterface
{
    /**
     * 文件路径
     * @var string
     */
    private $logfile;

    /**
     * 初始化
     * FileHandler constructor.
     */
    public function __construct()
    {
        parent::__construct();

        //初始化文件句柄
        $this->logfile = $this->path . $this->name . '.log';

        //添加日志文件切割任务
        $this->cutService();
    }

    /**
     * 刷入日志
     * @param array $data 日志数据
     */
    public function flush(array $data)
    {
        //格式化内容
        $text = Formatter::convert($data) . PHP_EOL;

        //写入文件
        $fp = fopen($this->logfile, 'a');
        fwrite($fp, $text);
        fclose($fp);
    }
}
