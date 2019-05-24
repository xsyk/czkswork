<?php
namespace Swork\Server\Http;

use Swork\Server\ArgumentInterface;
use Swork\Service;

class Argument implements ArgumentInterface
{
    /**
     * 是否已经结束输出
     * @var bool
     */
    private $isResponseEnd = false;

    /**
     * 请求URI
     * @var string
     */
    private $uri;

    /**
     * 用户简易路径的参数
     * @var array
     */
    private $params = [];

    /**
     * 用户URL请求参数
     * @var array
     */
    private $user_gets = [];

    /**
     * 用户form提交参数
     * @var array
     */
    private $user_posts = [];

    /**
     * Swoole原生请求
     * @var \swoole_http_request
     */
    public $swoole_request;

    /**
     * Swoole原生回应
     * @var \swoole_http_response
     */
    public $swoole_response;

    /**
     * Argument constructor.
     * @param string $uri 请求URI
     * @param \swoole_http_request $request Swoole原生请求
     * @param \swoole_http_response $response Swoole原生回应
     */
    public function __construct(string $uri, \swoole_http_request $request, \swoole_http_response $response)
    {
        $this->uri = $uri;
        $this->swoole_request = $request;
        $this->swoole_response = $response;
        $this->swoole_response->header('Content-Type', 'text/html;charset=utf-8');
        $this->user_gets = $request->get ?: [];
        $this->user_posts = $request->post ?: [];
    }

    /**
     * 获取参数值（优先从GET获取，然后再POST）
     * @param string $key 为空全部返回
     * @param string $dft 默认值
     * @return mixed
     */
    public function query($key = null, $dft = null)
    {
        if ($key == null)
        {
            return array_merge($this->user_gets, $this->user_posts);
        }
        if (isset($this->user_gets[$key]))
        {
            return $this->ensureDataType($this->user_gets[$key], $dft);
        }
        if (isset($this->user_posts[$key]))
        {
            return $this->ensureDataType($this->user_posts[$key], $dft);
        }
        return $dft;
    }

    /**
     * 获取GET值
     * @param string $key 为空全部返回
     * @param string $dft 默认值
     * @return mixed
     */
    public function get($key = null, $dft = null)
    {
        if ($key == null)
        {
            return $this->user_gets;
        }
        if (isset($this->user_gets[$key]))
        {
            return $this->ensureDataType($this->user_gets[$key], $dft);
        }
        return $dft;
    }

    /**
     * 获取POST表单
     * @param string $key
     * @param string $dft 默认值
     * @return mixed
     */
    public function post(string $key = null, $dft = null)
    {
        if ($key == null)
        {
            return $this->user_posts;
        }
        if (isset($this->user_posts[$key]))
        {
            return $this->ensureDataType($this->user_posts[$key], $dft);
        }
        return $dft;
    }

    /**
     * 获取Param值
     * @param string $key 为空全部返回
     * @param string $dft 默认值
     * @return mixed
     */
    public function param($key = null, $dft = null)
    {
        if ($key == null)
        {
            return $this->params;
        }
        if (isset($this->params[$key]))
        {
            return $this->ensureDataType($this->params[$key], $dft);
        }
        return $dft;
    }

    /**
     * 获取原始请求
     * @return string
     */
    public function raw()
    {
        return $this->swoole_request->rawContent();
    }

    /**
     * 发送文件到浏览器
     * @param string $filename 文件路径
     * @param string $name 输出文件名
     * @return mixed
     */
    public function sendFile($filename, $name = null)
    {
        //文件名
        $fileBaseName = basename($filename);

        //输出名默认和文件名一致
        if ($name == null)
        {
            $name = $fileBaseName;
        }

        //附上下载响应头
        $this->attachDownloadHeader($name);

        //输出并返回
        $this->isResponseEnd = true;
        return $this->swoole_response->sendfile($filename);
    }

    /**
     * 附上下载响应头
     * @param string $name
     */
    private function attachDownloadHeader($name)
    {
        //外部参数
        $response = $this->swoole_response;

        //取出文件后缀
        preg_match('/\.(.*)$/', $name, $matches);
        $suffix = $matches[1] ?? null;

        //文件类型对应mime
        $ctype = null;
        switch ($suffix)
        {
            case 'doc':
            case 'dot':
            case 'docs':
                $ctype = 'application/msword';
                break;
            case 'xls':
            case 'xlsx':
                $ctype = 'application/vnd.ms-excel';
                break;
            case 'ppt':
            case 'pptx':
                $ctype = 'application/vnd.ms-powerpoint';
                break;
            case 'pdf':
                $ctype = 'application/pdf';
                break;
            case 'jpg':
            case 'jpeg':
                $ctype = 'image/jpeg';
                break;
            case 'png':
                $ctype = 'image/png';
                break;
            default:
                $ctype = 'application/octet-stream';
                break;
        }

        //设置响应头
        $response->header('Content-Type', $ctype);
        $response->header('Content-Disposition', 'attachment;filename="' . $name . '"');
        $response->header('Cache-Control', 'max-age=0');
    }

    /**
     * 下载
     * @param mixed $content 内容
     * @param string $name
     * @throws
     */
    public function download($content, string $name)
    {
        //参数检查
        if (empty($name))
        {
            throw new \Exception('Download miss filename!');
        }

        //附上下载响应头
        $this->attachDownloadHeader($name);

        //下载文件
        $this->end($content);
    }

    /**
     * 结束输出
     * @param null $content
     */
    public function end($content = null)
    {
        if (!$this->isResponseEnd)
        {
            $this->isResponseEnd = true;

            //内容大小
            $length = strlen($content);

            //限制内容最大为100M
            if ($length > 104857600)
            {
                Service::$logger->error('Http response limit 100MB!');
                return;
            }

            //swoole_response的end方法有大小限制，切割文件逐个write再end
            //内容小于1兆则不分割
            if ($length < 1048576)
            {
                //输出
                $this->swoole_response->end($content);
            }
            else
            {
                $cursor = 0;
                $step = 524288; //分块为512k大小
                while ($sub = substr($content, $cursor, $step))
                {
                    $this->swoole_response->write($sub);
                    $cursor += $step;
                }

                //输出
                $this->swoole_response->end();
            }
        }
    }

    /**
     * 获取请求URI
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * 获取上传的文件
     * @param string $key
     * @return mixed
     */
    public function getFile(string $key)
    {
        return $this->swoole_request->files[$key];
    }

    /**
     * 获取请求IP
     * @return string
     */
    public function getUserIP()
    {
        $header = $this->swoole_request->header;
        $xForwardedFor = '';
        $xRealIp = '';
        $remoteAddr = '';
        foreach ($header as $key => $item)
        {
            $keyLower = strtolower($key);
            switch ($keyLower)
            {
                case 'x-forwarded-for':
                    $xForwardedFor = $item;
                    break;
                case 'x-real-ip':
                    $xRealIp = $item;
                    break;
                case 'remote_addr':
                    $remoteAddr = $item;
                    break;
                default:
                    break;
            }
        }
        $xForwardedFor = explode(',', $xForwardedFor);
        $fip = empty($xForwardedFor[0]) ? '' : trim($xForwardedFor[0]);
        if (empty($fip))
        {
            $fip = $xRealIp;
        }
        if (empty($fip))
        {
            $fip = $remoteAddr;
        }
        return $fip;
    }

    /**
     * 设置返回的Header
     * @param string $key 头KEY
     * @param string $value 具体内容
     */
    public function setHeader(string $key, string $value)
    {
        //没有完成响应才能设置响应头
        if (!$this->isResponseEnd)
        {
            $this->swoole_response->header($key, $value);
        }
    }

    /**
     * 获取Header内容
     * @param string $key 头KEY
     * @param mixed $dft 默认值
     * @return mixed
     */
    public function getHeader(string $key, $dft = '')
    {
        if (isset($this->swoole_request->header[$key]))
        {
            return $this->swoole_request->header[$key];
        }
        return $dft;
    }

    /**
     * 设置路径参数
     * @param array $values
     */
    public function setParams(array $values)
    {
        $this->params = $values;
    }

    /**
     * 设置用户GET请求参数
     * @param array $values
     */
    public function setGets(array $values)
    {
        foreach($values as $key => $value)
        {
            $this->user_gets[$key] = $value;
        }
    }

    /**
     * 设置用户POST请求参数
     * @param array $values
     */
    public function setPosts(array $values)
    {
        foreach($values as $key => $value)
        {
            $this->user_posts[$key] = $value;
        }
    }

    /**
     * 确保输入类型是按默认值的返回
     * @param mixed $value 获取到的值
     * @param mixed $refer 参考的值
     * @return bool|float|int
     */
    private function ensureDataType($value, $refer)
    {
        if ($refer === null)
        {
            return $value;
        }
        if (is_int($refer))
        {
            return intval($value);
        }
        if (is_float($refer))
        {
            return floatval($value);
        }
        if (is_bool($refer))
        {
            return boolval($value);
        }
        if (is_array($refer))
        {
            $tmp = [];
            $len = count($refer);
            if ($len == 0)
            {
                return is_array($value) ? $value : [];
            }
            if (is_int($refer[0]))
            {
                for ($idx = 0; $idx < $len; $idx++)
                {
                    $tmp[] = intval($value[$idx] ?? 0);
                }
            }
            elseif (is_float($refer[0]))
            {
                for ($idx = 0; $idx < $len; $idx++)
                {
                    $tmp[] = floatval($value[$idx] ?? 0.0);
                }
            }
            elseif (is_bool($refer[0]))
            {
                for ($idx = 0; $idx < $len; $idx++)
                {
                    $tmp[] = boolval($value[$idx] ?? false);
                }
            }
            else
            {
                for ($idx = 0; $idx < $len; $idx++)
                {
                    $tmp[] = $value[$idx] ?? '';
                }
            }
            return $tmp;
        }
        return $value;
    }
}
