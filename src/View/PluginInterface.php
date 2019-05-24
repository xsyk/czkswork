<?php
namespace Swork\View;

interface PluginInterface
{
    /**
     * 执行插件
     * @param string $value 原始值
     * @param string $param 插入参数
     * @return mixed
     */
    public function execute(string $value, string $param);
}