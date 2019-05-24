<?php
namespace Swork\Server\Tcp;

use Swork\Exception\RpcException;
use Swork\Server\Rpc\RpcService;
use Swork\Service;

class TcpReceive
{
    /**
     * @var \swoole_server
     */
    private $serv;

    /**
     * 全局环境变量
     * @var array
     */
    private $env;

    /**
     * Request constructor.
     * @param \swoole_server $serv
     * @param array $env
     */
    public function __construct(\swoole_server $serv, array $env)
    {
        $this->serv = $serv;
        $this->env = $env;
    }

    /**
     * TCP处理器
     * @param int $fd
     * @param int $from_id
     * @param string $cmd
     * @param mixed $data
     * @return mixed
     * @throws
     */
    function handler(int $fd, int $from_id, string $cmd, $data)
    {
        //解析执行
        $result = null;
        switch ($cmd)
        {
            case 'srv':
                $result = $this->processService($fd, $data);
                break;
            case 'task':
                $result = $this->processTask($fd, $data);
                break;
            case 'feed':
                $result = $this->processFeed($fd, $data);
                break;
            case 'push':
                $result = $this->processPush($fd, $data);
                break;
            case 'heart':
                $result = $this->processHeart($fd, $data);
                break;
            case 'memory':
                $result = $this->processMemory($fd, $data);
                break;
            default :
                throw new RpcException("Invalid cmd [$cmd]");
                break;
        }

        // 返回
        return $result;
    }

    /**
     * 处理远程调用的服务
     * @param int $fd
     * @param $data
     * @return mixed
     * @throws
     */
    private function processService(int $fd, $data)
    {
        return RpcService::process($data);
    }

    /**
     * 处理远程投递过来的任务
     * @param int $fd
     * @param $data
     * @return mixed
     */
    private function processTask(int $fd, $data)
    {
        if(Service::$server->worker_id == 0)
        {
            Service::$taskManager->remoteTask($data);
        }
        else
        {
            $info = [
                'act' => 'RemoteTask',
                'args' => $data
            ];
            Service::$server->sendMessage(serialize($info), 0);
        }
        return true;
    }

    /**
     * 处理远程任务处理完毕后的回调
     * @param int $fd
     * @param $data
     * @return mixed
     */
    private function processFeed(int $fd, $data)
    {
        if(Service::$server->worker_id == 0)
        {
            Service::$taskManager->finish($data);
        }
        else
        {
            $info = [
                'act' => 'FinishTask',
                'args' => $data
            ];
            Service::$server->sendMessage(serialize($info), 0);
        }
        return true;
    }

    /**
     * 处理由Slave服务发至Master服务器的任务
     * @param int $fd
     * @param $data
     * @return mixed
     */
    private function processPush(int $fd, $data)
    {
        if(Service::$server->worker_id == 0)
        {
            Service::$taskManager->push($data);
        }
        else
        {
            $info = [
                'act' => 'PushTask',
                'args' => $data
            ];
            Service::$server->sendMessage(serialize($info), 0);
        }
        return true;
    }

    /**
     * 获取服务角色状态
     * @param int $fd
     * @param $data
     * @return array
     */
    private function processHeart($fd, $data)
    {
        $id = $this->serv->table->get('id');
        $role = $this->serv->table->get('role');
        return [
            'id' => $id['data'] ?? '',
            'role' => $role['data'] ?? 0,
            'workers' => $this->env['task_worker_num'],
        ];
    }

    /**
     * 获取服务角色状态
     * @param int $fd
     * @param $data
     * @return bool
     */
    private function processMemory($fd, $data)
    {
        $key = $data['key'];
        $val = $data['val'];
        $this->serv->table->set($key, ['data' => $val]);
        return true;
    }
}
