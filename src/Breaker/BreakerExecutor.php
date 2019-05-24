<?php
namespace Swork\Breaker;

use Swork\Bean\Holder\InstanceHolder;

class BreakerExecutor
{
    public static function process(string $cls)
    {
        //外部参数
        $timefloat = microtime(true);
        $break = InstanceHolder::getClass($cls);

        //数据检查
        if ($break == false)
        {
            return;
        }

        //周期时间、周期内最大总量、最大频率、熔断时间、标识符
        $cycleTime = $break->cycleTime;
        $maxCount = $break->maxCount;
        $duration = $break->duration;
        $identifier = $break->getIdentifier();

        //上次记录时间、数量、上次截断时间
        $lastTime = $break->fetchLastTime($identifier);
        $count = $break->fetchCount($identifier);
        $lastBreakTime = $break->fetchLastBreakTime($identifier);

        //数量+1
        $break->putCount($identifier, $count + 1);

        //如果截断时间还没到期，继续截断
        $diffTime1 = ($timefloat - $lastBreakTime) * 1000;
        if ($diffTime1 < $duration)
        {
            $break->handleBreak($identifier);
            return;
        }

        //大于或等于周期时间
        $diffTime2 = ($timefloat - $lastTime) * 1000;
        if ($diffTime2 >= $cycleTime)
        {
            //数量归零、最后记录时间置入
            $break->putCount($identifier, 0);
            $break->putLastTime($identifier, $timefloat);

            //如果频率超出最大频率，记录最后截断时间、执行handleBreak
            if (($count / $diffTime2) >= ($maxCount / $cycleTime))
            {
                $break->putLastBreakTime($identifier, $timefloat);
                $break->handleBreak($identifier);
            }
        }
    }
}
