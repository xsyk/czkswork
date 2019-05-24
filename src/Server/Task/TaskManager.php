<?php
namespace Swork\Server\Task;

use Swork\Cluster\HeartBeat;
use Swork\Helper\ArrayHelper;
use Swork\Pool\Rpc\RpcDriver;
use Swork\Pool\Types;
use Swork\Server\Rpc\RpcPackage;
use Swork\Service;

/**
 * 任务器
 */
class TaskManager
{
    /**
     * 服务器配置
     * @var array
     */
    private $env;

    /**
     * 心跳服务
     * @var HeartBeat
     */
    private $beat;

    /**
     * 实例分布式任务分配器
     * @var TaskDispatch
     */
    private $dispatch;

    /**
     * 所有任务列表
     * @var array
     */
    private $tasks = [];

    /**
     * 正在执行中的任务列表
     * 0：类型（1：表示本地任务，2：分布式任务，3：远程投递任务）
     * 1：服务器ID（来源主服务器ID）
     * 2：任务开始时间（毫秒）
     * @var array
     */
    private $executes = [];

    /**
     * Timer constructor.
     * @param array $env 服务器配置
     */
    public function __construct(array $env)
    {
        $this->env = $env;
        $this->dispatch = new TaskDispatch();
    }

    /**
     * 设置任务列表
     * @param array $tasks
     */
    function setTasks(array $tasks)
    {
        $this->tasks = $tasks;
    }

    /**
     * 设置心跳对象
     * @param HeartBeat $beat 心跳对象
     */
    function setHeartBead(HeartBeat $beat)
    {
        $this->beat = $beat;
        $this->dispatch->setHeartBead($beat);
    }

    /**
     * 执行用户推进来的任务（手工调用：有可能是本地任务，也有可能是分布式任务）
     * @param array $task
     */
    function deliverTask(array $task)
    {
        //判断是否有集群服务器，如果没有则使用本地运行
        if ($this->beat != null)
        {
            //找到Master服务器
            $conn = $this->getMaster();

            //当前服务器ID
            $mid = $this->beat->getId();

            //如果没有主服务器，则本地执行
            if ($conn == null)
            {
                $this->localExecute($task);
            }

            //如果是主服务器，则使用本服务分发运行
            elseif ($conn['id'] == $mid)
            {
                $this->remoteExecute($mid, $task);
            }

            //不是主服务器，发回到主服务器运行
            else
            {
                $this->pushToMaster($conn['id'], $task);
            }
        }
        else
        {
            $this->localExecute($task);
        }
    }

    /**
     * 临时加入本地任务
     * @param int $time 时间间隔
     * @param string $cls 执行的命名空间
     * @param string $name 执行的类名
     * @param int $timeout 运行超时时间
     */
    function addTask(int $time, string $cls, string $name, int $timeout = 10)
    {
        $data = [
            'cls' => $cls,
            'name' => $name,
            'timeout' => $timeout
        ];
        Service::$server->tick($time, function () use ($data)
        {
            $this->localExecute($data);
        });
    }

    /**
     * 执行本地投递过来的任务（定时器产生）
     */
    function localTask()
    {
        foreach (($this->tasks['timer'] ?? []) as $time => $items)
        {
            if ($time <= 0)
            {
                continue;
            }
            Service::$server->tick($time, function () use ($items)
            {
                foreach ($items as $item)
                {
                    $this->localExecute($item);
                }
            });
        }
    }

    /**
     * 执行远程投递过来的任务
     * @param array $info
     */
    function remoteTask(array $info)
    {
        //主服务器ID（是哪个服务器发过来的）和 获取身份码和
        $id = $info['id'] ?? '';
        $md5 = $info['md5'] ?? '';
        $time = microtime(true);

        //组装数据
        $data = serialize($info);

        //投递任务（成功后放入执行池中,1：表示本地任务，2：分布式任务，3：远程投递任务）
        $tid = Service::$server->task($data);
        if ($tid > 0)
        {
            $this->executes[$md5] = [3, $id, $time];
        }
    }

    /**
     * 执行分布式任务
     * 不管是不是主服务器，都要定时器运行，仅是如果是主服务器，则运行任务
     */
    function clusterTask()
    {
        //当前服务器ID
        $mid = $this->beat->getId();

        //循环启动时间器
        foreach (($this->tasks['timer'] ?? []) as $time => $items)
        {
            if ($time <= 0)
            {
                continue;
            }

            //启动计时器
            Service::$server->tick($time, function () use ($mid, $items)
            {
                //判断自己是不是主服务
                $role = $this->beat->getRole();
                if ($role != 1)
                {
                    return;
                }

                //更新分配状态
                $this->dispatch->upgrade();

                //执行每个任务
                foreach ($items as $item)
                {
                    $this->remoteExecute($mid, $item);
                }
            });
        }
    }

    /**
     * 执行子任务
     * @param array $info
     */
    function slaveTask(array $info)
    {
        //找到Master服务器
        $conn = $this->getMaster();

        //当前服务器ID
        $mid = Service::getId();

        //如果没有主服务器，则本地执行
        if ($conn == null || $mid == null)
        {
            foreach ($info['args'] as $arg)
            {
                $info['args'] = [$arg];
                $this->localExecute($info);
            }
        }

        //如果是主服务器，则使用本服务分发运行
        elseif ($conn['id'] == $mid)
        {
            foreach ($info['args'] as $arg)
            {
                $info['args'] = [$arg];
                $this->remoteExecute($mid, $info);
            }
        }

        //不是主服务器，发回到主服务器运行
        else
        {
            $info['batch'] = true;
            $this->pushToMaster($conn['id'], $info);
        }
    }

    /**
     * 处理由Slave服务发至Master服务器的任务
     * @param array $info
     * @return bool
     */
    function push(array $info)
    {
        //当前服务器ID
        $mid = $this->beat->getId();

        //更新分配状态
        $this->dispatch->upgrade();

        //执行任务
        if (isset($info['batch']))
        {
            foreach ($info['args'] as $arg)
            {
                $info['args'] = [$arg];
                $this->remoteExecute($mid, $info);
            }
        }
        else
        {
            $this->remoteExecute($mid, $info);
        }

        //返回
        return true;
    }

    /**
     * 完成指定任务
     * @param string $md5
     * @return bool
     */
    function finish(string $md5)
    {
        //获取执行类型（1：表示本地任务，2：分布式任务，3：远程任务）
        $info = $this->executes[$md5] ?? false;

        //本地销毁
        unset($this->executes[$md5]);

        //回程通知主服务器：任务已经完成
        if ($info !== false && $info[0] == 3)
        {
            $this->notifyToMaster($md5, $info[1]);
        }

        //返回
        return true;
    }

    /**
     * 提取指定ID的连接配置
     * @param string $id 主服务器ID
     * @return \Swoole\Coroutine\Client
     */
    private function getServer(string $id)
    {
        //获取配置
        $conn = null;
        while (true)
        {
            $conn = ArrayHelper::getValues($this->beat->getConns(), $id, 'id');
            if ($conn == false)
            {
                continue;
            }
            $host = $conn['host'];
            $port = $conn['port'];
            break;
        }

        //连接服务器
        $cli = RpcDriver::connect(Types::Coroutine, $host, $port);

        //返回
        return $cli;
    }

    /**
     * 找到Master服务器
     * @return mixed
     */
    private function getMaster()
    {
        //如果不是分布式或还没有启动完成
        if ($this->beat == null)
        {
            return null;
        }

        //找到主服务器
        $conns = $this->beat->getConns();
        foreach ($conns as $key => $item)
        {
            if ($item['role'] == 1)
            {
                return $item;
            }
        }

        //没有找到
        return null;
    }

    /**
     * 把任务推送至本地运行
     * @param array $item 任务
     */
    private function localExecute(array $item)
    {
        //产生身份码
        $md5 = md5(serialize($item));

        //组装数据
        $info = [
            'cls' => $item['cls'],
            'name' => $item['name'],
            'group' => $item['group'] ?? '',
            'args' => $item['args'] ?? [],
            'md5' => $md5
        ];
        $data = serialize($info);
        $time = microtime(true);

        //检查是否有相同任务正在运行中
        if ($this->isRunning($md5, $item))
        {
            return;
        }

        //投递任务（成功后放入执行池中,1：表示本地任务，2：分布式任务）
        $tid = Service::$server->task($data);
        if ($tid > 0)
        {
            $this->executes[$md5] = [1, null, $time];
        }
    }

    /**
     * 把任务推送至远程运行
     * @param string $mid
     * @param array $item
     */
    private function remoteExecute(string $mid, array $item)
    {
        //产生身份码
        $md5 = md5(serialize($item));

        //组装数据
        $data = RpcPackage::serialize('task', [
            'id' => $mid,
            'cls' => $item['cls'],
            'name' => $item['name'],
            'group' => $item['group'] ?? '',
            'args' => $item['args'] ?? [],
            'md5' => $md5,
        ]);

        //检查是否有相同任务正在运行中
        if ($this->isRunning($md5, $item))
        {
            return;
        }

        //重试10次（可能存在连接不上的情况）
        for ($try = 0; $try < 10; $try++)
        {
            //找到最小任务量的连接
            $sid = $this->dispatch->pop();
            $time = microtime(true);

            //投递任务（成功后放入执行池中,1：表示本地任务，2：分布式任务）
            $rel = $this->dispatch->send($sid, $data);
            if ($rel == true)
            {
                $this->executes[$md5] = [2, null, $time];
                break;
            }
        }
    }

    /**
     * 把任务发送回至Master服务器
     * @param string $id 主服务器ID
     * @param array $task 任务
     */
    private function pushToMaster(string $id, array $task)
    {
        //找到主服务器连接配置
        $cli = $this->getServer($id);
        if ($cli === false)
        {
            Service::$logger->error('pushToMaster：Cann\'t connect server');
            return;
        }

        //发送回调数据
        if ($cli->send(RpcPackage::serialize('push', $task)))
        {
            $cli->recv(0.5);
        }

        //关闭
        $cli->close();
    }

    /**
     * 回程通知主服务器：任务已经完成
     * @param string $md5 任务身份码
     * @param string $id 服务器ID
     */
    private function notifyToMaster(string $md5, string $id)
    {
        //找到主服务器连接配置
        $cli = $this->getServer($id);
        if ($cli === false)
        {
            Service::$logger->error('feedbackMaster：Cann\'t connect server');
            return;
        }

        //发送回调数据
        if ($cli->send(RpcPackage::serialize('feed', $md5)))
        {
            $cli->recv(0.5);
        }
    }

    /**
     * 检查任务是否正在运行中
     * @param string $md5 任务KEY
     * @param array $item 任务内容
     * @return bool
     */
    private function isRunning(string $md5, array $item)
    {
        if (!isset($this->executes[$md5]))
        {
            return false;
        }
        if (microtime(true) - $this->executes[$md5][2] > $item['timeout'])
        {
            return false;
        }
        return true;
    }
}


