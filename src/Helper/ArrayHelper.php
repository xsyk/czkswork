<?php
namespace Swork\Helper;

class ArrayHelper
{
    /**
     * 把字符转换成数组
     * @param string $json
     * @return array|mixed
     */
    public static function toArray(string $json)
    {
        if (empty($json))
        {
            return [];
        }
        $obj = json_decode($json, true);
        if ($obj == false)
        {
            return [];
        }
        return $obj;
    }

    /**
     * 把二维数组指定列转换成字符串
     * @param array $array 二维码数组
     * @param string $key 指定key
     * @param string $glue join连接符
     * @return string
     */
    public static function toJoin(array $array, string $key, string $glue = ',')
    {
        if ($array == false)
        {
            return '';
        }
        return join($glue, array_unique(array_column($array, $key)));
    }

    /**
     * 把数组的值填充成默认值（支持一维和二维数组）
     * @param $list
     * @param array $from 要被替换的值
     * @param string $to 要替换成的字符
     */
    public static function fillDefaultValue(&$list, array $from = [''], string $to = '-')
    {
        if ($list == false)
        {
            return;
        }
        foreach ($list as $key => $item)
        {
            if (is_int($key))
            {
                foreach ($item as $idx => $val)
                {
                    foreach ($from as $src)
                    {
                        if ($val === $src)
                        {
                            $list[$key][$idx] = $to;
                            break;
                        }
                    }
                }
            }
            else
            {
                foreach ($from as $src)
                {
                    if ($item === $src)
                    {
                        $list[$key] = $to;
                        break;
                    }
                }
            }
        }
    }

    /**
     * 把数组的值填充成千分位（支持一维和二维数组）
     * @param $list
     * @param int $decimals 千分位保留几位小数点，默认0
     * @param array $cols 要被替换的字段数组，没有时默认全部
     */
    public static function fillThousandsSep(&$list, $decimals = 0, array $cols = [])
    {
        if ($list == false)
        {
            return;
        }
        foreach ($list as $key => $item)
        {
            if (is_int($key))
            {
                foreach ($item as $idx => $val)
                {
                    if (count($cols) == 0)
                    {
                        if (is_numeric($val) && $val > 1000)
                        {
                            $list[$key][$idx] = number_format($val, $decimals);
                        }
                    }
                    else
                    {
                        if (in_array($idx, $cols) && is_numeric($val) && $val > 1000)
                        {
                            $list[$key][$idx] = number_format($val, $decimals);
                        }
                    }
                }
            }
            else
            {
                if (count($cols) == 0)
                {
                    if (is_numeric($item) && $item > 1000)
                    {
                        $list[$key] = number_format($item, $decimals);
                    }
                }
                else
                {
                    if (in_array($key, $cols) && is_numeric($item) && $item > 1000)
                    {
                        $list[$key] = number_format($item, $decimals);
                    }
                }
            }
        }
    }

    /**
     * 从一维数组里面提取值数组
     * @param array $list
     * @param string $key 对应提取的KEY
     * @param null $dft 如果没有提取到的默认值
     * @return array
     */
    public static function map(array $list, string $key, $dft = null)
    {
        $data = array_map(function ($item) use ($key)
        {
            return $item[$key];
        }, $list);
        $data = array_unique($data);
        if ($dft != null && count($data) == 0)
        {
            $data[] = $dft;
        }
        return $data;
    }

    /**
     * 从多个一维数组里面提取值数组
     * @param array $lists 多个数组列表
     * @param array $keys 每个数组对应提取的KEY
     * @param null $dft 如果没有提取到的默认值
     * @return array|null
     */
    public static function maps(array $lists, array $keys, $dft = null)
    {
        $data = [];
        foreach ($lists as $idx => $list)
        {
            $key = $keys[$idx];
            $rel = array_map(function ($item) use ($key)
            {
                return $item[$key];
            }, $list);
            $data = array_merge($data, $rel);
        }
        $data = array_unique($data);
        if ($dft != null && count($data) == 0)
        {
            $data[] = $dft;
        }
        return $data;
    }

    /**
     * 合计数组中某个KEY的值
     * @param array $list
     * @param string $key 需要合计的KEY
     * @param bool $children 是否为统计子层
     * @return int
     */
    public static function sum(array $list, string $key, bool $children = false)
    {
        $cals = 0;
        foreach ($list as $item)
        {
            if ($children == false)
            {
                $cals += $item[$key] ?? 0;
            }
            else
            {
                foreach ($item as $value)
                {
                    $cals += $value[$key] ?? 0;
                }
            }
        }
        return $cals;
    }

    /**
     * 把一级数组列表形式变成字典形式（key-info 格式）
     * @param array $list 源一级数组列表
     * @param string $key 字典KEY的字段名
     * @return array
     */
    public static function dict(array $list, string $key)
    {
        $dict = [];
        foreach ($list as $item)
        {
            if (!isset($item[$key]))
            {
                continue;
            }
            $dict[$item[$key]] = $item;
        }
        return $dict;
    }

    /**
     * 把一级数组列表形式变成字典形式（key-list 格式）
     * @param array $list 源一级数组列表
     * @param string $key 字典KEY的字段名
     * @return array
     */
    public static function dicts(array $list, string $key)
    {
        $dict = [];
        foreach ($list as $item)
        {
            if (!isset($item[$key]))
            {
                continue;
            }
            $dict[$item[$key]][] = $item;
        }
        return $dict;
    }

    /**
     * 获取数据人第一个元素的值
     * @param array $list 一级或二级的数据
     * @param mixed $dft 默认值
     * @return mixed|null
     */
    public static function getFirst(array $list, $dft = null)
    {
        foreach ($list as $key => $value)
        {
            if (is_int($key))
            {
                $col = array_keys($value)[0] ?? false;
                if ($col == false)
                {
                    return $dft;
                }
                return $value[$col] ?? $dft;
            }
            return $value;
        }
        return $dft;
    }

    /**
     * 获取字典分类名称
     * @param array $array
     * @param mixed $id
     * @param string $key sorts的键值Key
     * @param string $val sorts的键值Name
     * @param string $dft 默认返回值
     * @return string
     */
    public static function getValue(array $array, $id, string $key = 'id', string $val = 'text', $dft = '')
    {
        foreach ($array as $value)
        {
            if ($value[$key] == $id && isset($value[$val]))
            {
                return $value[$val];
            }
        }
        return $dft;
    }

    /**
     * 获取字典分类名称
     * @param array $array
     * @param array|int|string $id 关联的值
     * @param string $key 关联的键
     * @return array
     */
    public static function getValues(array $array, $id, string $key = 'id')
    {
        $tmp = array();
        if (is_array($id))
        {
            foreach ($id as $item)
            {
                foreach ($array as $value)
                {
                    if ($value[$key] == $item)
                    {
                        $tmp[] = $value;
                    }
                }
            }
            return $tmp;
        }
        else
        {
            foreach ($array as $value)
            {
                if ($value[$key] == $id)
                {
                    $tmp = $value;
                    break;
                }
            }
        }
        return $tmp;
    }

    /**
     * 获取字典分类名称（多级）
     * 注：getValues目前兼容多级取值，但建议使用该函数
     * @param array $array
     * @param array|int|string $ids 关联的值
     * @param string $key 关联的键
     * @return array
     */
    public static function getValuesMulti(array $array, $ids, string $key = 'id')
    {
        $tmp = [];
        foreach ($ids as $item)
        {
            foreach ($array as $value)
            {
                if ($value[$key] == $item)
                {
                    $tmp[] = $value;
                }
            }
        }
        return $tmp;
    }
}
