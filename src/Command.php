<?php
namespace Swork;

use Swork\Bean\Scanner;
use Swork\Model\Generator;
use Swork\Pool\Rpc\RpcDriver;
use Swork\Pool\Types;
use Swork\Server\Rpc\RpcPackage;

class Command
{
    /**
     * 运行参数
     * @var array
     */
    private static $env;

    public static function Run(array $env)
    {
        self::$env = $env;

        //初始化配置
        Configer::collect($env);

        //初始化PID管理器
        Service::$pidManager = new PidManager(self::$env);

        //运行命令
        list($cmd, $opt) = self::getArgs();
        switch ($cmd)
        {
            case 'start':
                self::start($opt);
                break;
            case 'restart':
                self::restart($opt);
                break;
            case 'stop':
                self::stop($opt);
                break;
            case 'model':
                self::model($opt);
                break;
            case 'memory':
                self::memory($opt);
                break;
            case 'view':
                self::view($opt);
            case 'tasks':
                self::tasks($opt);
                break;
            default:
                self::showTag('ERR', 'not exist command');
        }
    }

    private static function start(array $opt)
    {
        //初始化服务
        $service = new Service(self::$env);
        $service->start($opt);
        self::showTag('Server', 'started');
        return true;
    }

    private static function restart(array $opt)
    {
        //关闭服务
        self::stop($opt);

        //启动服务
        $service = new Service(self::$env);
        $service->start($opt);
        self::showTag('Server', 'restarted');
        return true;
    }

    private static function stop($opt)
    {
        //是否强制停止
        $force = isset($opt['f']);

        //文件中取出pid组
        $pidArr = Service::$pidManager->readPidArray();

        //等待停止成功
        $awaitStop = function ($pid)
        {
            while (true)
            {
                //如果停止了，断开循环
                if (!\swoole_process::kill($pid, 0))
                {
                    break;
                }
            }
        };

        //区分普通停止和强制停止
        if ($force)
        {
            foreach ($pidArr as $key => $item)
            {
                self::showTag('stop force', "Killed pid: $item");
                if (\swoole_process::kill($item, 0))
                {
                    \swoole_process::kill($item, 9);
                }

                //等待停止成功
                $awaitStop($item);
            }
            self::showTag('stop force', "Completed!");
        }
        else
        {
            $master_pid = $pidArr['master'] ?? 0;
            if ($master_pid <= 0 || !\swoole_process::kill($master_pid, 0))
            {
                self::showTag('stop', "PID :{$master_pid} not exist");
                return false;
            }
            \swoole_process::kill($master_pid);

            //等待停止成功
            $awaitStop($master_pid);
        }

        //返回
        return true;
    }

    /**
     * 执行生成Model
     * @param array $opt
     */
    private static function model(array $opt)
    {
        $env = self::$env;
        go(function () use ($env, $opt)
        {
            $gen = new Generator($env);
            $gen->create($opt);
            die();
        });
    }

    /**
     * 执行生成Model
     * @param array $opt
     */
    private static function view(array $opt)
    {
        $env = self::$env;
        go(function () use ($env, $opt)
        {
            $scanner = new Scanner($env);
            $scanner->collect();
            $tasks = $scanner->getControllerHolder();
            die();
        });
    }

    /**
     * 执行生成Model
     * @param array $opt
     */
    private static function tasks(array $opt)
    {
        $env = self::$env;
        self::$env = $env;
        go(function () use ($env, $opt)
        {
            $scanner = new Scanner($env);
            $scanner->collect();
            $tasks = $scanner->getTaskHolder(false);

            //合并内容
            $tmp = [];
            foreach (($tasks['timer'] ?? []) as $tick => $items)
            {
                foreach ($items as $key => $item)
                {
                    $cls = $item['cls'];
                    $name = $item['name'];
                    $tmp[] = $cls . '::' . $name . ' => ' . $tick;
                }
            }
            sort($tmp);
            $txt = join("\r\n", $tmp);

            //写入文件
            $root = $env['root'];
            $sep = $env['sep'];

            //文件夹
            $path = $root . 'runtime' . $sep;
            if (!file_exists($path))
            {
                mkdir($path, 777, true);
            }

            //目标文件
            $fp = fopen($path . 'task.log', 'w+');
            fwrite($fp, $txt);
            fclose($fp);

            //页面输出
            foreach ($tmp as $value)
            {
                echo $value . "\r\n";
            }
        });
    }

    /**
     * 设置内存表的值
     * @param array $opt
     */
    private static function memory(array $opt)
    {
        //协程客户端
        $conn = RpcDriver::connect(Types::Normal, '127.0.0.1', 8099);

        //判断连接是否成功
        if ($conn === false)
        {
            self::showTag('memory', "TCP connect failed.");
            return;
        }

        foreach ($opt as $key => $value)
        {
            if ($conn->send(RpcPackage::serialize('memory', ['key' => $key, 'val' => $value])))
            {
                $rel = $conn->recv(0.5);
            }
        }
        self::showTag('memory', 'send done!');
    }

    /**
     * 外部命令参数
     * @return array
     */
    private static function getArgs()
    {
        global $argv;
        $command = '';
        $options = array();

        //提取命令
        if (isset($argv[1]))
        {
            $command = $argv[1];
        }

        //提取参数
        $args = join(' ', $argv);
        if (preg_match_all('/[\-]+(\w+)\s?([^\-\s]+)?/', $args, $match))
        {
            foreach ($match[1] as $key => $item)
            {
                $options[$item] = trim($match[2][$key]);
            }
        }

        //返回
        return array($command, $options);
    }

    /**
     * 屏幕输出内容
     * @param string $name 输出的KEY
     * @param string $value 输出的内容
     */
    private static function showTag($name, $value)
    {
        echo "\e[32m" . str_pad($name, 20, ' ', STR_PAD_RIGHT) . "\e[34m" . $value . "\e[0m\n";
    }
}
