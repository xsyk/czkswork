<?php
namespace Swork\Breaker;

interface BreakerInterface
{
    /**
     * 生成计算周期的标识符（为空表示所有请求）
     * @return string
     */
    public function getIdentifier();

    /**
     * 断开时执行
     * @param string $identifier 标识符
     * @return mixed
     */
    public function handleBreak(string $identifier);

    /**
     * 取到最后记录时间
     * @param string $identifier 标识符
     * @return float
     */
    public function fetchLastTime(string $identifier);

    /**
     * 保存最后记录时间
     * @param string $identifier 标识符
     * @param float $time 时间戳（带毫秒浮点类型）
     * @return mixed
     */
    public function putLastTime(string $identifier, float $time);

    /**
     * 取到数量
     * @param string $identifier 标识符
     * @return int
     */
    public function fetchCount(string $identifier);

    /**
     * 保存数量（保存）
     * @param string $identifier 标识符
     * @param int $count 数量
     * @return mixed
     */
    public function putCount(string $identifier, int $count);

    /**
     * 取到最后截断时间
     * @param string $identifier 标识符
     * @return float
     */
    public function fetchLastBreakTime(string $identifier);

    /**
     * 保存最后截断时间
     * @param string $identifier 标识符
     * @param float $time 时间戳（带毫秒浮点类型）
     * @return mixed
     */
    public function putLastBreakTime(string $identifier, float $time);
}
