<?php
namespace Swork\View;

class ViewHelper
{
    /**
     * 获取标签属性
     * @param string $text
     * @return array
     */
    public static function getProperties(string $text)
    {
        $list = [];
        preg_match_all('/\b(\w+)="?([^"\s]+)"?/', $text, $match);
        foreach ($match[1] as $key => $value)
        {
            $list[$value] = $match[2][$key];
        }
        return $list;
    }

    /**
     * 获取路径的文件夹合集
     * @param string $filepath
     * @return array
     */
    public static function getPathFolders(string $filepath)
    {
        $list = [];
        foreach (explode(DIRECTORY_SEPARATOR, $filepath) as $value)
        {
            if ($value == '')
            {
                continue;
            }
            $list[] = $value;
        }
        return $list;
    }

    /**
     * 过滤无效、多余的分隔符
     * @param string $path
     * @return string
     */
    public static function filterSeparator(string $path)
    {
        $sep = DIRECTORY_SEPARATOR;
        $path = str_replace('\\', $sep, $path);
        $parts = [];
        foreach (explode($sep, $path) as $value)
        {
            if ($value == '')
            {
                continue;
            }
            $parts[] = $value;
        }
        return join($sep, $parts);
    }

    /**
     * 清除单引号文本
     * @param string $text
     * @return string
     */
    public static function cleanQuoteText(string $text)
    {
        return trim(str_replace('\'', '\\\'', $text));
    }
}