<?php
namespace Swork;

class PidManager
{
    /**
     * pid文件名
     * @var string
     */
    private $filename;

    /**
     * @param array $env 环境
     * PidManager constructor.
     */
    public function __construct(array $env)
    {
        $filename = $env['root'] . 'runtime' . $env['sep'] . 'swork.pid';
        if (!file_exists($filename))
        {
            touch($filename);
        }
        $this->filename = $filename;
    }

    /**
     * 获取pid数组（[manager:xxx,master:xxx,worker1:xxx,task1:xxx]）
     * @return array
     */
    public function readPidArray()
    {
        $content = file_get_contents($this->filename);
        preg_match_all('/(\w+)=(\w+)/', $content, $matches);
        $arr = [];
        if (count($matches) >= 3)
        {
            foreach ($matches[1] as $key => $item)
            {
                $arr[$item] = $matches[2][$key];
            }
        }
        return $arr;
    }

    /**
     * 保存pid数组至文件
     * @param array $arr pid数组
     * @return bool
     */
    public function writePidArray(array $arr)
    {
        //组装字符串
        $strArr = [];
        foreach ($arr as $key => $item)
        {
            $strArr[] = "$key=$item";
        }
        $joinStr = join(',', $strArr);
        return file_put_contents($this->filename, $joinStr);
    }

    /**
     * 追加进程id
     * @param string|int $pid 进程id
     * @param string $name
     * @return bool
     */
    public function appendPid($pid, string $name)
    {
        $arr = $this->readPidArray();
        $arr[$name] = $pid;
        return $this->writePidArray($arr);
    }

    /**
     * 删除进程id
     * @param string|int $pid 进程id
     * @return bool
     */
    public function deletePid($pid)
    {
        $arr = $this->readPidArray();
        $newArr = [];
        foreach ($arr as $key => $item)
        {
            if ($item != $pid)
            {
                $newArr[$key] = $item;
            }
        }
        unset($arr);
        return $this->writePidArray($newArr);
    }

    /**
     * 清理内容
     * @return bool
     */
    public function clear()
    {
        return file_put_contents($this->filename, '');
    }

    /**
     * 回调
     * @param array $args [method:xxx,args:xxx]
     * @return mixed
     */
    public function handler(array $args)
    {
        try
        {
            $method = $args['method'];
            $params = $args['params'] ?? [];
            return $this->$method(...$params);
        }
        catch (\Throwable $throwable)
        {
            var_dump($throwable->getMessage());
        }
        return null;
    }

    /**
     * 发起
     * @param string $method 函数名
     * @param array $params 参数
     */
    public function invoke($method, array $params = [])
    {
        $data = serialize([
            'act' => 'PidManager',
            'args' => ['method' => $method, 'params' => $params]
        ]);
        Service::$server->sendMessage($data, 0);
    }
}
