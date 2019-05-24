<?php
namespace Swork\Server;

/**
 * 定时器
 */
class Timer
{
    /**
     * @var \swoole_server
     */
    private $serv;

    /**
     * Timer constructor.
     * @param \swoole_server $serv
     */
    public function __construct(\swoole_server $serv)
    {
        $this->serv = $serv;
    }

    /**
     * 执行定时任务
     * @param array $tasks
     */
    function tickTask(array $tasks)
    {
        foreach (($tasks['timer'] ?? []) as $time => $items)
        {
            $this->serv->tick($time, function () use ($items)
            {
                foreach ($items as $item)
                {
                    $info = [
                        'act' => 'TimerTask',
                        'cls' => $item['cls'],
                        'name' => $item['name'],
                    ];
                    $this->serv->task(serialize($info));
                }
            });
        }
    }
}
