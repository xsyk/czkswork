<?php
namespace Swork\View\Plugin;

use Swork\View\PluginInterface;

/**
 * 在原始值上做计算字符长度
 * Class CountPlugin
 * @package Swork\View
 */
class CountPlugin implements PluginInterface
{
    /**
     * 执行插件
     * @param string $value 原始值
     * @param string $param 插入参数
     * @return mixed
     */
    public function execute(string $value, string $param)
    {
        return 'strlen(' . $value . ')';
    }
}