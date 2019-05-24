<?php
namespace Swork\Behavior;

interface FilterInterface
{
    /**
     * 数据过滤
     * @param string $sql 执行的SQL语句
     * @param array $data 需要处理的数据
     */
    public function process(string &$sql, array &$data);
}
