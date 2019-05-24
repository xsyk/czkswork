<?php
namespace Swork\Helper;

class StringHelper
{
    /**
     * 产生随时数
     * @param int $len 长度位数
     * @param int $type 字符类型（0：纯数字，1：大小写字母，2：数字大小写字母）
     * @return string
     */
    public static function rand(int $len = 5, int $type = 2)
    {
        static $chars = [
            '0123456789',
            'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
        ];
        static $sizes = [10, 52, 62];

        //输出
        $outs = array();
        for($i = 0; $i < $len; $i++)
        {
            $outs[] = $chars[$type][mt_rand(0, $sizes[$type] - 1)];
        }

        //返回
        return join('', $outs);
    }

    /**
     * 判断是否以某字符开始
     * @param string $haystack 完整的字符串
     * @param string $needle 检查的字符段
     * @return bool
     */
    public static function startWith(string $haystack, string $needle)
    {
        return substr($haystack, 0, strlen($needle)) == $needle;
    }

    /**
     * 将字符串转换UTF8编码
     * @param string $str 需转换字符串
     * @return string
     */
    public static function gbk2utf8($str)
    {
        return mb_convert_encoding($str, 'UTF-8', 'GBK');
    }

    /**
     * 将字符串转换GBK编码
     * @param string $str 需转换字符串
     * @return string
     */
    public static function utf82gbk($str)
    {
        return mb_convert_encoding($str, 'GBK', 'UTF-8');
    }

    /**
     * 检查字符串是否为数字
     * @param string $str 需要验证的字符串
     * @return bool
     */
    public static function isNumber($str)
    {
        return preg_match('/^\-?[\d\.]+$/', $str);
    }

    /**
     * 判断是否为合法的邮件
     * @param string $str 要检查的字符
     * @return int
     */
    public static function isEmail($str)
    {
        return preg_match('/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/', $str);
    }

    /*
     *  检查字符串是否为数字集合
     *  如 3,4,5,7
     * @return 0 or 1
     */
    public static function isNumberArray($str)
    {
        return preg_match('/^(\d+)(,\d+)*$/', $str);
    }

    /*
     *  检查字符串是否为KEY类型
     *  如 abc
     * @return 0 or 1
     */
    public static function isTextKey($str)
    {
        return preg_match('/^[a-zA-Z0-9_\-\/%]+$/', $str);
    }

    /**
     * 是否包含非法字符
     * @param string $str 字符串
     * @return int 0 or 1
     */
    public static function isIllegalChar($str)
    {
        return preg_match('/^[=\?\'%]$/', $str);
    }

    /**
     * 转换成INT类型
     * @param $val string
     * @return int
     */
    public static function toInt($val)
    {
        if (is_numeric($val))
        {
            return intval($val);
        }
        return 0;
    }

    /**
     * 转换成Double类型
     * @param $val string
     * @return float
     */
    public static function toDouble($val)
    {
        if (is_numeric($val))
        {
            return doubleval($val);
        }
        return 0.0;
    }
}
