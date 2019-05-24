<?php
namespace Swork\View\Plugin;

use Swork\View\PluginInterface;

/**
 * 在原始数值上做加法
 * Class AddPlugin
 * @package Swork\View
 */
class AddPlugin implements PluginInterface
{
    /**
     * 执行插件
     * @param string $value 原始值
     * @param string $param 插入参数
     * @return mixed
     */
    public function execute(string $value, string $param)
    {
        return '(' . $param . '+' . $value . ')';
    }
}