<?php
namespace Swork\Behavior;

interface RecorderInterface
{
    /**
     * 数据记录器
     * @param float $stime 执行开始时间
     * @param float $etime 执行结束时间
     * @param mixed $data 需要处理的数据
     * @param \Throwable|null 异常信息
     * @return mixed
     */
    public function process(float $stime, float $etime, $data, $throwable);
}
