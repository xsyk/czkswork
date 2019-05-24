<?php
namespace Swork\View\Plugin;

use Swork\View\PluginInterface;

/**
 * 在原始值上做进行日期格式化
 * Class DatePlugin
 * @package Swork\View
 */
class DatePlugin implements PluginInterface
{
    /**
     * 执行插件
     * @param string $value 原始值
     * @param string $param 插入参数
     * @return mixed
     */
    public function execute(string $value, string $param)
    {
        return 'date(\''. $param .'\', ' . $value . ')';
    }
}