<?php
namespace Swork\Helper;

/**
 * 数字格式化工具
 * Class NumberHelper
 * @package Swork\Helper
 */
class NumberHelper
{
    /**
     * 保留小数点后N位
     * @param $val
     * @param int $dec 小数位数（默认2位）
     * @param bool $float 是否转换成float类型（默认false值）
     * @return float
     */
    public static function format($val, int $dec = 2, bool $float = false)
    {
        if (is_string($val))
        {
            $val = floatval($val);
        }

        //如果是0
        if ($val == 0)
        {
            return 0;
        }

        //保留2位小数
        $val = number_format($val, $dec, '.', '');
        if($float)
        {
            return floatval($val);
        }

        //返回
        return $val;
    }
}
