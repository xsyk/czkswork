<?php
namespace Swork\Helper;

/**
 * 加解密工具
 * Class EncryptHelper
 * @package Swork\Helper
 */
class EncryptHelper
{
    /**
     * 类型1：加密
     * @param string $string 原文
     * @param string $key 密文KEY（32位）
     * @return string
     */
    public static function Type1Encode(string $string, string $key)
    {
        //动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
        $ckey_length = 4;

        //密匙a会参与加解密
        $keya = md5(substr($key, 0, 16));

        //密匙b会用来做数据完整性验证
        $keyb = md5(substr($key, 16, 16));

        //密匙c用于变化生成的密文
        $keyc = substr(md5(microtime()), -$ckey_length);

        //参与运算的密匙
        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);

        //明文，前16位用来保存$keyb(密匙b)，
        //解密时会通过这个密匙验证数据完整性
        //如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
        $string = substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);
        $result = '';
        $box = range(0, 255);
        $rndkey = array();

        //产生密匙簿
        for ($i = 0; $i <= 255; $i++)
        {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
        for ($j = $i = 0; $i < 256; $i++)
        {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        // 核心加解密部分
        for ($a = $j = $i = 0; $i < $string_length; $i++)
        {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;

            // 从密匙簿得出密匙进行异或，再转成字符
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        // 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
        // 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
        return $keyc . str_replace('=', '', base64_encode($result));
    }

    /**
     * 类型1：解密
     * @param string $string 密文
     * @param string $key 密文KEY（32位）
     * @return bool|string
     */
    public static function Type1Decode(string $string, string $key)
    {
        // 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
        $ckey_length = 4;

        // 密匙a会参与加解密
        $keya = md5(substr($key, 0, 16));

        // 密匙b会用来做数据完整性验证
        $keyb = md5(substr($key, 16, 16));

        // 密匙c用于变化生成的密文
        $keyc = substr($string, 0, $ckey_length);

        // 参与运算的密匙
        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);

        //解密时会通过这个密匙验证数据完整性
        //如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存动态密匙，以保证解密正确
        $string = base64_decode(substr($string, $ckey_length));
        $string_length = strlen($string);
        $result = '';
        $box = range(0, 255);
        $rndkey = array();

        //产生密匙簿
        for ($i = 0; $i <= 255; $i++)
        {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
        for ($j = $i = 0; $i < 256; $i++)
        {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        // 核心加解密部分
        for ($a = $j = $i = 0; $i < $string_length; $i++)
        {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;

            //从密匙簿得出密匙进行异或，再转成字符
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        // 验证数据有效性，请看未加密明文的格式
        if (substr($result, 0, 16) == substr(md5(substr($result, 16) . $keyb), 0, 16))
        {
            return substr($result, 16);
        }
        else
        {
            return '';
        }
    }
}
