<?php
namespace Swork\Bean;

use Swork\Bean\Holder\InstanceHolder;
use Swork\Bean\Holder\UsingHolder;

class Reflection
{
    /**
     * 实例化注释的属性
     * @param string $arg 类名（全路径）或已实例化对象
     * @param object $inc 已实例化好的类
     * @throws \ReflectionException
     */
    public static function property($arg, $inc)
    {
        $rc = new \ReflectionClass($arg);
        $cls = '\\' . $rc->getName();
        foreach ($rc->getProperties() as $property)
        {
            $doc = $property->getDocComment();
            if ($doc != false)
            {
                if (preg_match_all('/@(\w+)\((.*)\)/', $doc, $match))
                {
                    foreach ($match[1] as $key => $item)
                    {
                        $param = trim(rtrim($match[2][$key], '/'), '"');
                        switch ($item)
                        {
                            case 'Inject':
                                self::inject($inc, $property, $cls, $doc);
                                break;
                            case 'Reference':
                                self::reference($inc, $property, $cls, $doc, $param);
                                break;
                        }
                    }
                }
            }
        }
    }

    /**
     * 注入方法
     * @param $inc
     * @param \ReflectionProperty $property
     * @param string $cls 类的命令空间
     * @param string $doc 方法名的注析
     * @throws
     */
    private static function inject($inc, \ReflectionProperty $property, string $cls, string $doc)
    {
        if (preg_match('/\@var\s+(.+)[\r\n]/', $doc, $match))
        {
            $arg = UsingHolder::getClass($cls, trim($match[1]));
            $obj = InstanceHolder::getClass($arg);
            $property->setAccessible(true);
            $property->setValue($inc, $obj);
        }
    }

    /**
     * @param $inc
     * @param \ReflectionProperty $property
     * @param string $cls 类的命令空间
     * @param string $doc 方法名的注析
     * @param string $param Reference的参数
     */
    private static function reference($inc, \ReflectionProperty $property, string $cls, string $doc, string $param)
    {
        if (preg_match('/\@var\s+(.+)[\r\n]/', $doc, $match))
        {
            //获取接口的全名称
            $arg = UsingHolder::getClass($cls, trim($match[1]));

            //生成实例化KEY（引用连接池+接口全名称）
            $key = $param . ':' . $arg;

            //如果储存，则直接赋值
            if (InstanceHolder::isExist($key))
            {
                $obj = InstanceHolder::getClass($key);
                $property->setAccessible(true);
                $property->setValue($inc, $obj);
            }
            else
            {
                $rpc = new \Swork\Server\Rpc\RpcClient();
                $rpc->setInstance($param);
                $rpc->setInterface($arg);
                InstanceHolder::setClass($key, $rpc);
                $property->setAccessible(true);
                $property->setValue($inc, $rpc);
            }
        }
    }
}