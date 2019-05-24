<?php
namespace Swork\Helper;

class HttpHelper
{
    /**
     * 通过GET的方式连接数据
     * @param string $url 目标URL
     * @param null $data 需要提交的数据
     * @param null $options 配置参数
     * @return array|mixed
     */
    public static function get(string $url, $data = null, $options = null)
    {
        $options['post'] = false;
        return self::curl($url, $data, $options);
    }

    /**
     * 通过POST的方式连接数据
     * @param string $url 目标URL
     * @param null $data 需要提交的数据
     * @param null $options 配置参数
     * @return array|mixed
     */
    public static function post(string $url, $data = null, $options = null)
    {
        $options['post'] = true;
        return self::curl($url, $data, $options);
    }

    /**
     * 通过Curl方式提交数据
     * @param string $url 目标URL
     * @param null $data 提交的数据
     * @param null |array $options 配置参数
     *                             timeout 超时，默认3秒
     *                             header 请求头信息 如：array("Content-Type: application/json")
     *                             incookie 需要提交上去的Cookie内容（本地文件）
     *                             outcookie 请求反馈的Cookie内容（本地文件）
     *                              把结果转把JSON数据返回 true, false （默认false）
     *                             post 是否表单传输 true, false （默认false）
     * @return array|mixed
     */
    public static function curl(string $url, $data = null, $options = null)
    {
        //传输方式
        $post = $options['post'] ?? false;

        //处理地址（如果不是POST方式，但有Data时处理至URL后面）
        if ($post == false && $data != null)
        {
            if (is_array($data))
            {
                $data = http_build_query($data);
            }
            if (strpos($url, '?') > 0)
            {
                $url .= '&' . $data;
            }
            else
            {
                $url .= '?' . $data;
            }
        }

        //初始化curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        //设置超时
        curl_setopt($curl, CURLOPT_TIMEOUT, ($options['timeout'] ?? 3));

        //设置请求头
        if (count($options['header'] ?? []) > 0)
        {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $options['header']);
        }

        //如果是POST方式
        if ($post == true)
        {
            curl_setopt($curl, CURLOPT_POST, TRUE);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        //设置Cookie
        if (($options['incookie'] ?? null) != null)
        {
            curl_setopt($curl, CURLOPT_COOKIEFILE, $options['incookie']);
        }
        if (($options['outcookie'] ?? null) != null)
        {
            curl_setopt($curl, CURLOPT_COOKIEJAR, $options['outcookie']);
        }

        //如果使用HTTPS请求
        if (strpos(strtolower($url), 'https') === 0)
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        }

        //运行curl，获取结果
        $result = @curl_exec($curl);

        //关闭句柄
        curl_close($curl);

        //转成数组
        if (($options['json'] ?? false) == true)
        {
            return json_decode($result, true);
        }

        //返回结果
        return $result;
    }
}
