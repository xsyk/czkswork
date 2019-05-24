<?php
namespace Swork;

use Swork\Bean\Holder\BreakerHolder;
use Swork\Bean\Holder\ExceptionHandlerHolder;
use Swork\Bean\Holder\MiddlewareHolder;
use Swork\Bean\Holder\ControllerHolder;
use Swork\Bean\Holder\InstanceHolder;
use Swork\Bean\Holder\ServiceHolder;
use Swork\Bean\Holder\TaskHolder;
use Swork\Bean\Holder\UsingHolder;
use Swork\Bean\Holder\ValidateHolder;
use Swork\Bean\Scanner;
use Swork\Cluster\HeartBeat;
use Swork\Exception\RpcException;
use Swork\Helper\DateHelper;
use Swork\Helper\StringHelper;
use Swork\Logger\Logger;
use Swork\Process\Reload;
use Swork\Server\Http\Argument;
use Swork\Server\Http\Request;
use Swork\Server\Rpc\RpcPackage;
use Swork\Server\Task\TaskManager;
use Swork\Server\Tcp\TcpReceive;

/**
 * 服务入口
 * Class Service
 * @package Swork
 */
class Service
{
    /**
     * 全局环境参数
     * @var array
     */
    public static $env;

    /**
     * swoole服务对象
     * @var \Swoole\Server
     */
    public static $server;

    /**
     * 日志对象
     * @var Logger
     */
    public static $logger;

    /**
     * 进程PID管理器
     * @var PidManager
     */
    public static $pidManager;

    /**
     * 任务管理器
     * @var TaskManager
     */
    public static $taskManager;

    /**
     * 是否开启定时任务
     * @var bool
     */
    private $timer_task = false;

    /**
     * 集群服务配置
     * @var array array
     */
    private $cluster_srvs = [];

    /**
     * 是否开启热更新
     * @var bool
     */
    private $auto_reload = false;

    /**
     * Service constructor.
     * @param array $env
     */
    public function __construct($env)
    {
        self::$env = $env;
    }

    /**
     * 主进程启动
     * @param \swoole_server $serv
     */
    public function onStart(\swoole_server $serv)
    {
    }

    /**
     * 应用终止
     * @param \swoole_server $serv
     */
    public function onShutdown(\swoole_server $serv)
    {
        //清理pid文件
        self::$pidManager->clear();
    }

    /**
     * Worker进程启动（含Task进程）
     * @param \swoole_server $serv
     * @param $workder_id
     */
    public function onWorkerStart(\swoole_server $serv, $workder_id)
    {
        //加载Bean容器
        $scanner = new Scanner(self::$env, $workder_id);
        $scanner->collect();
        ExceptionHandlerHolder::setHolder($scanner->getExceptionHandlerHolder());
        MiddlewareHolder::setHolder($scanner->getMiddlewareHolder());
        ControllerHolder::setHolder($scanner->getControllerHolder());
        ControllerHolder::setRegex($scanner->getUriMatchHolder());
        ValidateHolder::setHolder($scanner->getValidateHolder());
        ServiceHolder::setHolder($scanner->getServiceHolder());
        BreakerHolder::setHolder($scanner->getBreakerHolder());
        UsingHolder::setHolder($scanner->getUsingHolder());
        TaskHolder::setHolder($scanner->getTaskHolder());

        //加载连接配置
        $inti = new Initialize();
        $inti->db();
        $inti->redis();
        $inti->rpc();

        //仅在主线程时执行
        if ($workder_id == 0)
        {
            //保存manager_pid、master_pid、当前worker_pid至文件
            self::$pidManager->appendPid($serv->master_pid, 'master');
            self::$pidManager->appendPid($serv->manager_pid, 'manager');
            self::$pidManager->appendPid($serv->worker_pid, 'worker0');

            //初始化任务管理器
            self::$taskManager = new TaskManager(self::$env);
            self::$taskManager->setTasks($scanner->getTaskHolder());

            //集群模式下启动心跳
            $clusters = count($this->cluster_srvs);
            if ($clusters > 0)
            {
                $beat = new HeartBeat(self::$env);
                self::$taskManager->setHeartBead($beat);
            }

            //如果开启了任务
            if ($this->timer_task)
            {
                //判断是否有集群服务器，如果没有则使用本地运行
                if ($clusters > 0)
                {
                    self::$taskManager->clusterTask();
                }
                else
                {
                    self::$taskManager->localTask();
                }
            }

            //如果开启代码热更新
            if ($this->auto_reload)
            {
                $reload = new Reload($serv, self::$env);
                $reload->init();
            }
        }
        else
        {
            //记录非0号worker的PID（先发回0号worker，防止文件并行读写出现异常）
            $pidName = ($serv->taskworker ? 'task' : 'worker') . $workder_id;
            self::$pidManager->invoke('appendPid', [$serv->worker_pid, $pidName]);
        }

        //记录日志
        self::$logger->info("worker [$workder_id] start");
    }

    /**
     * 进程终止
     * @param \swoole_server $serv
     * @param $workder_id
     */
    public function onWorkerStop(\swoole_server $serv, $workder_id)
    {
        //worker终止后清理文件中的pid
        if ($workder_id == 0)
        {
            self::$pidManager->deletePid($serv->worker_pid);
        }
        else
        {
            self::$pidManager->invoke('deletePid', [$serv->worker_pid]);
        }
    }

    /**
     * 通道通讯
     * @param \swoole_server $serv
     * @param int $src_worker_id
     * @param $data
     */
    public function onPipeMessage(\swoole_server $serv, int $src_worker_id, $data)
    {
        $info = unserialize($data);
        $act = $info['act'] ?? '';
        $args = $info['args'];
        switch ($act)
        {
            case 'AddTask':
                self::$taskManager->addTask(...$args);
                break;
            case 'DeliverTask':
                self::$taskManager->deliverTask($args);
                break;
            case 'RemoteTask':
                self::$taskManager->remoteTask($args);
                break;
            case 'SlaveTask':
                self::$taskManager->slaveTask($args);
                break;
            case 'FinishTask':
                self::$taskManager->finish($args);
                break;
            case 'PushTask':
                self::$taskManager->push($args);
                break;
            case 'RunTask':
                self::$server->task($args);
                break;
            case 'PidManager':
                self::$pidManager->handler($args);
                break;
        }
    }

    /**
     * 异步任务
     * @param \swoole_server $serv
     * @param int $task_id
     * @param int $worker_id
     * @param $data
     * @return mixed
     * @throws
     */
    public function onTask(\swoole_server $serv, int $task_id, int $worker_id, $data)
    {
        //echo 'onTask-' . microtime(true) . PHP_EOL;

        //解压内容
        $info = unserialize($data);
        if ($info == false)
        {
            self::$logger->error("onTask no data");
            return null;
        }

        //提取参数
        $cls = $info['cls'];
        $name = $info['name'];
        $args = $info['args'] ?? [];
        $md5 = $info['md5'] ?? '';
        $group = $info['group'] ?? '';

        //获取实例
        $inc = InstanceHolder::getClass($cls);

        //捕捉异常，防止发生异常下次不执行
        try
        {
            //执行任务
            $result = $inc->$name(...$args);

            //判断是否是分裂子任务
            $slave = TaskHolder::getSalve($group);
            if ($slave != null && is_array($result))
            {
                $slave['md5'] = $md5;
                $slave['args'] = $result;
                if (Service::$server->worker_id == 0)
                {
                    self::$taskManager->slaveTask($slave);
                }
                else
                {
                    $info = [
                        'act' => 'SlaveTask',
                        'args' => $slave,
                    ];
                    Service::$server->sendMessage(serialize($info), 0);
                }
            }
        }
        catch (\Throwable $e)
        {
            self::$logger->error("onTask $cls::$name error", [$e->getMessage()]);
        }

        //完成
        return $md5;
    }

    /**
     * 当任务执行完成时
     * @param \swoole_server $serv
     * @param $task_id
     * @param $data
     */
    public function onFinish(\swoole_server $serv, $task_id, $data)
    {
        go(function () use ($serv, $data)
        {
            self::$taskManager->finish($data);
        });
    }

    public function onConnect(\swoole_server $serv, $fd)
    {
        //echo 'onSwooleConnect-' . microtime(true) . PHP_EOL;
    }

    public function onClose(\swoole_server $serv, $fd)
    {
        //echo 'onSwooleClose-' . microtime(true) . PHP_EOL;
    }

    /**
     * TCP接收数据
     * @param \swoole_server $serv
     * @param int $fd 连接句柄
     * @param int $from_id
     * @param string $data 请求的数据
     * @throws
     */
    public function onReceive(\swoole_server $serv, $fd, $from_id, $data)
    {
        //初始化
        $cmd = null;
        $res = null;
        $error = null;

        //捕捉异常
        try
        {
            //数据解包
            $info = RpcPackage::unserialize($data);
            if ($info == false)
            {
                throw new RpcException('Rpc receive empty data');
            }
            if (empty($info['cmd']))
            {
                throw new RpcException('Rpc receive miss [cmd]');
            }
            if (!isset($info['data']) && !is_null($info['data']))
            {
                throw new RpcException('Rpc receive miss [data]');
            }
            $cmd = $info['cmd'];

            //处理
            $tcp = new TcpReceive($serv, self::$env);
            $res = $tcp->handler($fd, $from_id, $cmd, $info['data']);
        }
        catch (\Throwable $e)
        {
            $error = [
                'code' => $e->getCode(),
                'msg' => $e->getMessage()
            ];
            self::$logger->error("onReceive error", [$e->getMessage()]);
        }

        //数据组包
        $pack = RpcPackage::serialize($cmd, $res, $error);

        //回送数据
        $serv->send($fd, $pack);
    }

    /**
     * URL请求回调
     * @param \swoole_http_request $request
     * @param \swoole_http_response $response
     * @throws
     */
    public function onRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
        //开始时间
        $startTime = DateHelper::getTime();

        //封装参数
        $uri = strtolower($request->server['request_uri']);
        $argument = new Argument($uri, $request, $response);

        //过滤ico请求
        if ($uri == '/favicon.ico')
        {
            $argument->end();
            return;
        }

        //生成日志context
        $makeLoggerContext = function ($merge = []) use ($startTime, $argument)
        {
            return array_merge([
                'ET' => sprintf('%.3f', DateHelper::getTime() - $startTime),
                'IP' => $argument->getUserIP()
            ], $merge);
        };

        //处理URL请求
        $result = '';
        try
        {
            //路由调度
            $http = new Request(self::$env);
            $http->setUri($uri);
            $result = $http->handler($argument);

            //记录正常日志
            self::$logger->info($uri, $makeLoggerContext());
        }
        catch (\Throwable $throwable)
        {
            //异常处理
            $inc = ExceptionHandlerHolder::getClass($throwable);
            $result = $inc->handler($argument, $throwable);

            //其他参数
            $merge = [
                'GET' => $request->get,
                'EMSG' => $throwable->getMessage(),
                'ECODE' => $throwable->getCode(),
                'FILE' => $throwable->getFile(),
                'LINE' => $throwable->getLine()
            ];

            //调试模式下打印出文件名和行数
            if (!self::$env['deamon'])
            {
                $merge['TRACE'] = $throwable->getTraceAsString();
            }

            //记录异常日志
            self::$logger->error($uri, $makeLoggerContext($merge));
        }
        finally
        {
            //清空当前协程上下文记录，防止内存泄露
            Context::delete();

            //输出
            if (is_array($result))
            {
                $result = json_encode($result);
            }
            $argument->end($result);
        }
    }

    /**
     * 获取内存的值
     * @param string $key
     * @return mixed
     */
    public static function getMemory(string $key)
    {
        if (!isset(self::$server->table))
        {
            return false;
        }
        $data = self::$server->table->get($key);
        if ($data == false)
        {
            return false;
        }
        return $data['data'] ?? false;
    }

    /**
     * 获取当前服务器ID
     * @return string
     */
    public static function getId()
    {
        $id = self::$server->table->get('id');
        return $id['data'] ?? '';
    }

    /**
     * 启动服务器
     * @param array $opt 启动参数
     */
    public function start(array $opt)
    {
        //外部参数
        $sep = self::$env['sep'];
        $root = self::$env['root'];

        //获取服务器配置
        $server_conf = Configer::get('server');
        $httpserver = $server_conf['httpserver'];
        $tcpserver = $server_conf['tcpserver'];

        //设置服务器配置
        $this->timer_task = $server_conf['timer_task'] ?? false;
        $this->cluster_srvs = $server_conf['cluster_srvs'] ?? [];
        $this->auto_reload = $server_conf['auto_reload'] ?? false;
        self::$env = array_merge(self::$env, $server_conf);
        self::$env['deamon'] = $deamon = isset($opt['d']);;

        //确保文件夹存在
        $runtime_path = $root . 'runtime' . $sep;
        $log_path = $runtime_path . 'logs' . $sep;
        if (!file_exists($log_path))
        {
            mkdir($log_path, 0777, true);
        }
        self::$env['log_path'] = $log_path;

        //swoole配置
        $opts = [
            'daemonize' => $deamon,
            'worker_num' => $server_conf['worker_num'],
            'task_worker_num' => $server_conf['task_worker_num'],
            'log_file' => $log_path . 'swoole.log',
            'enable_static_handler' => true,
            'document_root' => $root . 'static'
        ];
        if (isset($server_conf['socket_buffer_size']))
        {
            $opts['socket_buffer_size'] = $server_conf['socket_buffer_size'];
        }
        if (isset($server_conf['buffer_output_size']))
        {
            $opts['buffer_output_size'] = $server_conf['buffer_output_size'];
        }
        if (isset($server_conf['package_max_length']))
        {
            $opts['package_max_length'] = $server_conf['package_max_length'];
        }

        //定义共享表
        $table = new \swoole_table(1024);
        $table->column('data', \swoole_table::TYPE_STRING, 64);
        $table->create();

        //服务器ID
        $table->set('id', ['data' => StringHelper::rand(5)]);

        //初始化日志
        self::$logger = new Logger(self::$env);

        //HTTP Server
        $http_server = new \swoole_http_server($httpserver['host'], $httpserver['port']);
        $http_server->set($opts);
        $http_server->on('Start', [$this, 'onStart']);
        $http_server->on('Shutdown', [$this, 'onShutdown']);
        $http_server->on('Task', [$this, 'onTask']);
        $http_server->on('Finish', [$this, 'onFinish']);
        $http_server->on('Request', [$this, 'onRequest']);
        $http_server->on('WorkerStart', [$this, 'onWorkerStart']);
        $http_server->on('WorkerStop', [$this, 'onWorkerStop']);
        $http_server->on('PipeMessage', [$this, 'onPipeMessage']);
        self::$server = $http_server;

        //TCP Server
        $tcp_server = $http_server->addListener($tcpserver['host'], $tcpserver['port'], SWOOLE_SOCK_TCP);
        $tcp_server->set([
            'task_worker_num' => 0,
            'open_eof_check' => true,
            'package_eof' => "\r\n\r\n",
            'package_max_length' => 1024 * 1024 * 2, //2M
        ]);
        $tcp_server->on('Connect', [$this, 'onConnect']);
        $tcp_server->on('Close', [$this, 'onClose']);
        $tcp_server->on('Receive', [$this, 'onReceive']);

        //绑定数据表
        $http_server->table = $table;

        //Start
        $http_server->start();
    }
}


