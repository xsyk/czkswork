<?php
namespace Swork;

/**
 * 配置管理器
 * Class Configer
 * @package Swork
 */
class Configer
{
    /**
     * 收集器
     * @var array
     */
    private static $collector = [];

    /**
     * 收集
     * @param array $env 环境
     */
    public static function collect($env)
    {
        //配置文件所在的目录
        $configDir = $env['root'] . 'config' . $env['sep'];

        //目录下所有php文件列表
        $list = glob($configDir . '*.php');
        foreach ($list as $filename)
        {
            $basename = basename($filename);
            $name = str_replace('.php', '', $basename);
            if (empty($name))
            {
                continue;
            }
            $config = @include($filename);
            self::$collector[$name] = $config;
        }
    }

    /**
     * 取出（支持可深度取值）
     * @param string $name 键名（如：db:test:uri）
     * @param mixed $dft 默认值
     * @return mixed
     */
    public static function get(string $name, $dft = [])
    {
        $keys = explode(':', $name);
        $value = self::$collector;
        foreach ($keys as $key)
        {
            $value = $value[$key] ?? $dft;
        }
        return $value;
    }
}


