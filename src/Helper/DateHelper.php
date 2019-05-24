<?php
namespace Swork\Helper;

class DateHelper
{
    /**
     * 格式化时间
     * @param int $time
     * @param string $format
     * @param string $dft
     * @return false|string
     */
    public static function toString(int $time, string $format = 'Y-m-d H:i', string $dft = '-')
    {
        if ($time <= 0)
        {
            return $dft;
        }
        return date($format, $time);
    }

    /**
     * 获取当前时间，精确至毫秒
     * @return mixed
     */
    public static function getTime()
    {
        return round(microtime(true), 3);
    }

    /**
     * 检查字符串是否为yyyy-MM-dd日期类型
     *  如 2018-05-30,2018-5-30
     * @param $str
     * @return false|int
     */
    public static function isDate($str)
    {
        return preg_match('/^\d{4}\-\d{1,2}\-\d{1,2}$/', $str);
    }

    /*
    *  检查字符串是否为yyyy-MM-dd H:i:s 或 yyyy-MM-dd H:i 日期时间类型
    *  如 2018-05-30 12:22:33 或 2018-05-30 12:22
    * @return 0 or 1
    */
    public static function isDateTime($str)
    {
        return preg_match('/^\d{4}\-\d{1,2}\-\d{1,2}\s\d{1,2}:\d{1,2}(:\d{1,2})?$/', $str);
    }

    /**
     * 格式化时间，【今天、昨天、前天】
     * @param string|int $timestamp 时间戳
     * @param string|array $format 格式化规则；数组：[今天,三天内,三天外但当年内,当年外]，字符串则表示所有的都用一个格式
     * @param string $zero 时间戳==0时输出的内容，为null时表示输出格式化时间(如1970-1-1)
     * @return string 格式化时间
     */
    public static function formatLately($timestamp, $format = 'Y-m-d H:i', $zero = null)
    {
        //参数检查
        if ($timestamp == 0 && $zero !== null)
        {
            return $zero;
        }

        //索引
        $fidx = 0;

        //今天起始时间戳、今年起始时间戳
        $today = strtotime(date('Ymd'));
        $year = strtotime(date('Y') . '/1/1');

        //时间区间（全闭合）
        $intervals = array(
            array(
                'day' => '今天',
                'stime' => $today,
                'etime' => $today + 86399
            ),
            array(
                'day' => '昨天',
                'stime' => $today - 86400,
                'etime' => $today - 1
            ),
            array(
                'day' => '前天',
                'stime' => $today - 172800,
                'etime' => $today - 86401
            )
        );

        //是否在三天内
        $day = null;
        foreach ($intervals as $key => $item)
        {
            if ($timestamp >= $item['stime'] && $timestamp <= $item['etime'])
            {
                $fidx = $key == 0 ? 0 : 1;
                $day = $item['day'];
                break;
            }
        }

        //是否在当年内（优先三天内）
        if ($day == null && $timestamp >= $year)
        {
            $fidx = 2;
        }

        //是否在当年外（优先三天内）
        if ($day == null && $timestamp < $year)
        {
            $fidx = 3;
        }

        //用于格式化的字符串
        if (is_array($format))
        {
            $format = isset($format[$fidx]) ? $format[$fidx] : '';
        }

        //将日期部分用今天、昨天、前天代替
        if ($day !== null)
        {
            $format = preg_replace('/(Y|y)?.*(M|m).*(D|d)/', $day, $format);
        }

        //返回
        return date($format, $timestamp);
    }
}
