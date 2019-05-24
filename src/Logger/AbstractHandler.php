<?php
namespace Swork\Logger;

use Swork\Service;

/**
 * Class AbstractHandler
 * @package Swork\Logger
 */
class AbstractHandler
{
    protected $name;
    protected $path;
    protected $file;
    protected $keeps;

    /**
     * 启动之时的文件名（用于判断是否需要切割日志）
     * @var string
     */
    private $startFile;

    /**
     * 初始化
     * FileHandler constructor.
     */
    public function __construct()
    {
        $env = Service::$env;
        $conf = Service::$logger->getConf();

        //提取参数
        $this->path = $env['log_path'];
        $this->file = $conf['file'] ?? '';
        $this->keeps = $conf['keeps'] ?? 0;
        $this->name = $conf['name'] ?? 'logger';
        if($this->file == '')
        {
            $this->file = 'Ymd-H';
        }
        if($this->keeps <= 0)
        {
            $this->keeps = 24;
        }

        //记录启动时的文件名
        $this->startFile = date($this->file);
    }

    /**
     * 日志文件切割
     * 把今天的日志文件，处理成按指定日期格式储存，并按指定份数储存
     */
    public function cutfile()
    {
        //如果启动时与现在时的文件名格式相同
        if($this->startFile == date($this->file))
        {
            return;
        }

        //清理Logger
        $this->clearKeeps($this->name);
        $this->clearFiles($this->name);

        //清理Swoole
        $this->clearKeeps('swoole');
        $this->clearFiles('swoole');

    }

    /**
     * 使用日志切割服务
     */
    protected function cutService()
    {
        $args = [1000, AbstractHandler::class, 'cutfile'];
        if(Service::$server->worker_id == 0)
        {
            Service::$taskManager->addTask(...$args);
        }
        else
        {
            $info = [
                'act' => 'AddTask',
                'args' => $args
            ];
            Service::$server->sendMessage(serialize($info), 0);
        }
    }

    /**
     * 清理日志文件份数（仅保留有限份数）
     * @param string $name 文件默认前缀名字
     */
    protected function clearKeeps(string $name)
    {
        //判断是否超过份数
        $files = glob($this->path . $name .'-*.log');
        $diffs = count($files) - $this->keeps;
        if($diffs <= 0)
        {
            return;
        }

        //删除超过份数的
        asort($files);
        for($i=0; $i < $diffs; $i++)
        {
            unlink($files[$i]);
        }
    }

    /**
     * 清理日志文件内容（把当前文件移至指定文件格式之下）
     * @param string $name 文件默认前缀名字
     */
    private function clearFiles(string $name)
    {
        $oldfile = $this->path . $name .'-'. $this->startFile .'.log';
        $curfile = $this->path . $name .'.log';

        //如果没有内容
        if(!file_exists($curfile) || filesize($curfile) == 0)
        {
            return;
        }

        //打开旧日志文件并把文件写入
        $oldfp = fopen($oldfile, 'a');
        $curfp = fopen($curfile, 'r');

        while(!feof($curfp))
        {
            fwrite($oldfp, fread($curfp, 102400));
        }

        fclose($curfp);
        fclose($oldfp);

        //清空原文件
        file_put_contents($curfile, '');
    }
}
