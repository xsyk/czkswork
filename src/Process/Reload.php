<?php
namespace Swork\Process;

use Swork\Service;

class Reload
{
    private $serv = null;
    private $pidFile = '';
    private $watchDir = '';
    private $currentMd5 = '';
    private $interval = 1000;

    public function __construct(\swoole_server $serv, array $env)
    {
        $this->serv = $serv;
        $this->pidFile = $env['root'] . $env['sep'] . 'runtime/swork.pid';
        $this->watchDir = $env['root'] . $env['sep'] . 'app';
    }

    /**
     * 初始化
     */
    public function init()
    {
        $this->currentMd5 = self::md5File($this->watchDir);
        $this->serv->tick($this->interval, function () {
            $currentMd5 = self::md5File($this->watchDir);
            if (strcmp($this->currentMd5, $currentMd5) !== 0)
            {
                $this->killMaster();
                $this->serv->reload();
                $this->currentMd5 = $currentMd5;
                echo 'Reloaded' . PHP_EOL;
            }
        });
    }

    /**
     * 读取文件md5值
     * @param string $dir
     * @return bool|string
     */
    private function md5File($dir)
    {
        if (!is_dir($dir))
        {
            return '';
        }

        $md5File = [];
        $d = dir($dir);
        while (false !== ($entry = $d->read()))
        {
            if ($entry !== '.' && $entry !== '..')
            {
                if (is_dir($dir . '/' . $entry))
                {
                    $md5File[] = self::md5File($dir . '/' . $entry);
                }
                elseif (substr($entry, -4) === '.php')
                {
                    $md5File[] = md5_file($dir . '/' . $entry);
                }
                $md5File[] = $entry;
            }
        }
        $d->close();

        return md5(implode('', $md5File));
    }

    /**
     * kill掉主进程
     */
    private function killMaster()
    {
        $arr = Service::$pidManager->readPidArray();
        if (isset($arr['master']))
        {
            posix_kill($arr['master'], 0);
        }
    }
}
