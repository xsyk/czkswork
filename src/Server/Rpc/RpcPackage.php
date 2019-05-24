<?php
namespace Swork\Server\Rpc;

class RpcPackage
{
    /**
     * 打包数据
     * 采用EOF方式解决分包问题
     * @param string $cmd
     * @param array|mixed $data 数据
     * @param $error array|null 错误信息，{code:Int,msg:String}
     * @return string
     */
    public static function serialize(string $cmd, $data, $error = null)
    {
        $info = [
            'cmd' => $cmd,
            'data' => $data
        ];
        if ($error != null)
        {
            $info['error'] = $error;
        }
        return serialize($info) . "\r\n\r\n";
    }

    /**
     * 解包数据
     * @param string $data
     * @return array|bool
     */
    public static function unserialize(string $data)
    {
        if ($data == '')
        {
            return false;
        }
        $data = trim($data);
        return unserialize($data);
    }
}
