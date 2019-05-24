<?php
namespace Swork\Logger;

/**
 * Class Formatter
 * @package Swork\Logger
 */
class Formatter
{
    /**
     * 格式化日志格式
     * @param array $data
     * @return string
     */
    public static function convert(array $data)
    {
        //提取参数
        $time = $data['time'];
        $level = $data['level'];
        $message = $data['message'];
        $context = $data['context'];

        //拆分参数
        $nums = explode('.', $time);
        $t1 = $nums[0];
        $t2 = '000';
        if (count($nums) >= 2)
        {
            $t2 = str_pad($nums[1], 3, '0', STR_PAD_RIGHT);
        }

        $span = date('Y-m-d H:i:s', $t1) . '.' . $t2;
        $lname = Levels::get($level);

        //返回格式
        return sprintf("[$span]-[$lname] $message %s", json_encode($context, JSON_UNESCAPED_UNICODE));
    }
}
