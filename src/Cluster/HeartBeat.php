<?php
namespace Swork\Cluster;

use Swoole\Coroutine;
use Swork\Helper\DateHelper;
use Swork\Pool\Rpc\RpcDriver;
use Swork\Pool\Types;
use Swork\Server\Rpc\RpcPackage;
use Swork\Service;

/**
 * 集群心跳器
 */
class HeartBeat
{
    /**
     * 服务器配置
     * @var array
     */
    private $env;

    /**
     * @var array|\Swoole\Client
     */
    private $conns;

    /**
     * Timer constructor.
     * @param array $env 服务器配置
     */
    public function __construct(array $env)
    {
        $this->env = $env;
        $this->check();
    }

    /**
     * 检查服务器角色
     */
    private function check()
    {
        //预装连接
        $clusters = $this->env['cluster_srvs'];
        foreach ($clusters as $cluster)
        {
            list($host, $port) = explode(':', $cluster);
            $this->conns[$cluster] = [
                'host' => $host,
                'port' => $port,
                'id' => '',
                'stat' => 0,
                'role' => -1,
                'workers' => -1,
                'uptime' => -1
            ];
        }

        //每500毫秒检查一次
        Service::$server->tick(500, function () use ($clusters) {

            //检查每个服务
            foreach ($clusters as $cluster)
            {
                $this->tick($cluster);
            }

            //选举主服务
            $this->voteMaster();
//            $this->showResult();
        });
    }

    private function tick(string $cluster)
    {
        //连接服务器
        $cli = $this->connect($cluster);
        if($cli == false)
        {
            $this->failResult($cluster);
            return;
        }

        //发送心跳命令
        $success = false;
        if($cli->send(RpcPackage::serialize('heart', 'xx')))
        {
            $rel = $cli->recv(0.5);
            if($rel != false)
            {
                $info = RpcPackage::unserialize($rel);
                if($info != false)
                {
                    $success = true;
                    $this->successResult($cluster, $info);
                }
            }
        }

        //关闭连接
        $cli->close();

        //如果有异常
        if($success == false)
        {
            $this->failResult($cluster);
        }
    }

    /**
     * 无法连接或无法获取远程服务器数据
     * @param string $cluster
     */
    private function failResult(string $cluster)
    {
        $this->conns[$cluster]['stat'] = 0;
        $this->conns[$cluster]['role'] = -1;
    }

    /**
     * 成功获取远程服务器数据
     * @param string $cluster
     * @param array $info
     */
    private function successResult(string $cluster, array $info)
    {
        $this->conns[$cluster]['id'] = $info['data']['id'] ?? '';
        $this->conns[$cluster]['stat'] = 1;
        $this->conns[$cluster]['role'] = $info['data']['role'] ?? 0;
        $this->conns[$cluster]['workers'] = $info['data']['workers'] ?? -1;
        $this->conns[$cluster]['uptime'] = DateHelper::getTime();
    }

    /**
     * 选择Master服务器
     */
    private function voteMaster()
    {
        //检查是否有Master
        $masters = [];
        $enables = [];
        foreach ($this->conns as $key => $item)
        {
            if($item['role'] == 1)
            {
                $masters[] = $key;
            }
            else if($item['role'] != -1)
            {
                $enables[] = $key;
            }
        }
        $countMasters = count($masters);
        $countEnables = count($enables);

        //如果没有
        if($countMasters == 0 && $countEnables > 0)
        {
            //把第一个设置成Master
            $this->setMaster($enables[0]);

            //其它设置成Slave
            $this->setSlave($enables);
        }

        //如果多于1个主
        else if($countMasters > 1)
        {
            $this->setSlave($masters);
        }

        //有非Slave服务的
        else if($countEnables > 0)
        {
            $this->setSlave($enables, false);
        }
    }

    private function showResult()
    {
        echo 'HeartBeat: '. PHP_EOL;
        foreach ($this->conns as $key => $item)
        {
            echo $key ." id:". $item['id'] ." stat:". $item['stat'] ." role:". $item['role'] ."  workers:". $item['workers'] ."  uptime:". $item['uptime'] . PHP_EOL;
        }
        echo PHP_EOL;
    }

    /**
     * 设置为主服务器
     */
    private function setMaster(string $masterId)
    {
        $id = $this->getId();
        foreach ($this->conns as $key => $item)
        {
            if($key == $masterId && $id == $item['id'])
            {
                Service::$server->table->set('role', ['data' => 1]);
            }
        }
    }

    /**
     * 设置为从服务器
     * @param array $list
     * @param bool $removeFirst 去掉第一个
     */
    private function setSlave(array $list, bool $removeFirst = true)
    {
        //把第一个去掉
        if($removeFirst == true)
        {
            array_splice($list, 0, 1);
        }
        if(count($list) == 0)
        {
            return;
        }

        //找到自己
        $id = $this->getId();
        foreach ($this->conns as $key => $item)
        {
            if(in_array($key, $list) && $id == $item['id'])
            {
                Service::$server->table->set('role', ['data' => 2]);
            }
        }
    }

    /**
     * 获取服务器角色
     * 1：Master
     * 2：Slave
     * @return int
     */
    function getRole()
    {
        $id = Service::$server->table->get('role');
        return $id['data'] ?? '';
    }

    /**
     * 获取服务器ID
     * @return string
     */
    function getId()
    {
        $id = Service::$server->table->get('id');
        return $id['data'] ?? '';
    }

    /**
     * 获取当前连接对象
     * @return array|\Swoole\Client
     */
    function getConns()
    {
        return $this->conns;
    }

    /**
     * 连接服务器
     * @param string $cluster
     * @return bool|Coroutine\Client
     */
    function connect(string $cluster)
    {
        //获取配置
        $host = $this->conns[$cluster]['host'];
        $port = $this->conns[$cluster]['port'];

        //获取服务器
        $cli = RpcDriver::connect(Types::Coroutine, $host, $port);

        //返回
        return $cli;
    }
}


