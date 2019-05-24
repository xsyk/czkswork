<?php
namespace Swork\Helper;

/**
 * XML处理器
 * Class XmlHelper
 * @package Swork\Helper
 */
class XmlHelper
{
    /**
     * 将数组转xml
     * @param array $data 数组
     * @param string $tag 根标签名
     * @return string
     */
    public static function toString($data, $tag = 'xml')
    {
        if (!is_array($data) || count($data) <= 0)
        {
            return '';
        }
        $xml = "<$tag>";
        foreach ($data as $key => $val)
        {
            if (is_numeric($val))
            {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            }
            elseif (is_array($val))
            {
                $xml .= "<" . $key . ">" . self::toString($val['item'], 'item') . "</" . $key . ">";
            }
            else
            {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</$tag>";
        return $xml;
    }

    /**
     * 将xml转为array
     * @param string $xml
     * @return mixed
     */
    public static function toArray($xml)
    {
        if (!$xml)
        {
            return [];
        }

        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);

        //返回
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }
}
