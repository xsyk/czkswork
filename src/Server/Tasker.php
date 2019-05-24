<?php
namespace Swork\Server;

use Swork\Service;

/**
 * 任务器
 */
class Tasker
{
    /**
     * 推送任务
     * @param string $cls 任务所在CLASS
     * @param string $name 执行方法的名称
     * @param array $args 执行方法的参数
     * @param int $timeout 执行超时时间（单位秒）
     */
    public static function deliver(string $cls, string $name, array $args = [], int $timeout = 10)
    {
        //类名前补斜线
        if (strpos($cls, '\\') !== 0)
        {
            $cls = "\\$cls";
        }

        //组装数据
        $info = [
            'cls' => $cls,
            'name' => $name,
            'args' => $args,
            'timeout' => $timeout
        ];

        //投递任务
        if (Service::$server->taskworker || Service::$server->worker_id != 0)
        {
            $data = [
                'act' => 'DeliverTask',
                'args' => $info
            ];
            Service::$server->sendMessage(serialize($data), 0);
        }
        else
        {
            Service::$taskManager->deliverTask($info);
        }
    }
}


