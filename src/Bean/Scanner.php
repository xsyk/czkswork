<?php
namespace Swork\Bean;

use Demo\App\Exception\AppException;
use Swork\Bean\Annotation\Validate;
use Swork\Configer;
use Swork\Server\Http\Argument;
use Swork\View\Compiler;

/**
 * 注释入口
 */
class Scanner
{
    /**
     * 全局环境配置
     * @var array
     */
    private $env;

    /**
     * 工作进程ID
     * @var int
     */
    private $workerId;

    /**
     * 文件分隔符
     * @var string
     */
    private $sep;

    /**
     * 文件开始尝试
     */
    private $sdep;

    /**
     * 注解方法的容器
     * @var array
     */
    private $exceptionHandlerHolder = [];
    private $middlewareHolder = [];
    private $controllerHolder = [];
    private $validateHolder = [];
    private $uriMatchHolder = [];
    private $serviceHolder = [];
    private $usingHolder = [];
    private $taskHolder = [];
    private $breakerHolder = [];
    private $globalMiddlewareParams = [];

    /**
     * 模板视图编译器
     * @var Compiler
     */
    private $viewCompiler = null;

    /**
     * Scanner constructor.
     * @param array $env 运行环境参数
     * @param int $workerId 工作进程序号ID
     */
    public function __construct(array $env, int $workerId)
    {
        $this->env = $env;
        $this->sep = $env['sep'];
        $this->workerId = $workerId;
        $this->viewCompiler = new Compiler($env);
    }

    /**
     * 开始收集所有文件
     * @throws
     */
    public function collect()
    {
        $path = $this->env['root'] . 'app';
        $this->sdep = strlen($path) + 1;
        $this->loadFiles($path);

        //冒泡把全局中间件重排
        $globalMiddlewares = $this->middlewareHolder['global'] ?? [];
        $len = count($globalMiddlewares);
        for ($n = 1; $n <= $len; $n += 1)
        {
            for ($j = 0; $j < $len - $n; $j += 1)
            {
                $nextJ = $j + 1;
                $jParam = $this->globalMiddlewareParams[$globalMiddlewares[$j]] ?? '';
                $nextJParam = $this->globalMiddlewareParams[$globalMiddlewares[$nextJ]] ?? '';
                if (intval($jParam) < intval($nextJParam))
                {
                    //数值大的排到前面
                    $temp = $globalMiddlewares[$nextJ];
                    $globalMiddlewares[$nextJ] = $globalMiddlewares[$j];
                    $globalMiddlewares[$j] = $temp;
                }
            }
        }
        unset($this->globalMiddlewareParams);
        $this->middlewareHolder['global'] = $globalMiddlewares;
    }

    public function getExceptionHandlerHolder()
    {
        return $this->exceptionHandlerHolder;
    }

    public function getMiddlewareHolder()
    {
        return $this->middlewareHolder;
    }

    public function getControllerHolder()
    {
        return $this->controllerHolder;
    }

    public function getUriMatchHolder()
    {
        return $this->uriMatchHolder;
    }

    public function getValidateHolder()
    {
        return $this->validateHolder;
    }

    public function getServiceHolder()
    {
        return $this->serviceHolder;
    }

    public function getUsingHolder()
    {
        return $this->usingHolder;
    }

    /**
     * @param bool $reconf 是否重置配置
     * @return array
     */
    public function getTaskHolder(bool $reconf = true)
    {
        $list = $this->taskHolder;
        if ($reconf == true)
        {
            //从配置文件中取出
            $conf = Configer::get('task');
            foreach (($list['timer'] ?? []) as $tick => $items)
            {
                if ($tick <= 0)
                {
                    unset($list['timer'][$tick]);
                    continue;
                }
                foreach ($items as $key => $item)
                {
                    $cls = substr($item['cls'], 1);
                    $name = $item['name'];
                    if (($conf[$cls][$name] ?? false) != true)
                    {
                        unset($list['timer'][$tick][$key]);
                    }
                }
            }
        }
        return $list;
    }

    public function getBreakerHolder()
    {
        return $this->breakerHolder;
    }

    /**
     * @param string $path
     * @throws \ReflectionException
     */
    private function loadFiles(string $path)
    {
        if (substr($path, -1, 1) != $this->sep)
        {
            $path .= $this->sep;
        }
        if (false != ($handle = opendir($path)))
        {
            while (false !== ($file = readdir($handle)))
            {
                if (substr($file, 0, 1) == '.')
                {
                    continue;
                }
                $file = $path . $file;
                if (is_file($file))
                {
                    $this->readDocument($file);
                }
                else
                {
                    $this->loadFiles($file);
                }
            }
            closedir($handle);
        }
    }

    /**
     * 读取文件，并进行分析
     * @param string $file
     * @throws \ReflectionException
     */
    private function readDocument(string $file)
    {
        if (!preg_match('/^(.+)\.php$/i', substr($file, $this->sdep), $match))
        {
            return;
        }
        $parts = explode($this->sep, $match[1]);

        //载入并初始化class
        $nsPrefix = Configer::get('frame:ns_prefix', 'App\\');
        $cls = '\\' . $nsPrefix . join('\\', $parts);
        $rc = new \ReflectionClass($cls);

        //解析引用空间
        $this->analyzeUsing($rc, $cls, $file);

        //解析Class
        $this->analyzeClass($rc, $cls);
    }

    /**
     * 解析use命令空间
     * @param \ReflectionClass $rc
     * @param string $cls
     * @param string $file
     */
    private function analyzeUsing(\ReflectionClass $rc, string $cls, string $file)
    {
        //加入默认的命令空间
        $list['#'] = '\\' . $rc->getNamespaceName();

        //正则解析use的别名引用
        $text = file_get_contents($file);
        if ($text != false)
        {
            if (preg_match_all('/use\s+(.+)\\\(\w+);/', $text, $match))
            {
                foreach ($match[2] as $key => $item)
                {
                    $list[$item] = '\\' . $match[1][$key] . '\\' . $item;
                }
            }
            if (preg_match_all('/use\s+(.+)\s+as\s+(\w+);/', $text, $match))
            {
                foreach ($match[2] as $key => $item)
                {
                    $list[$item] = '\\' . $match[1][$key];
                }
            }
        }

        //压入容器中
        $this->usingHolder[$cls] = $list;
    }

    /**
     * 解析Class类
     * @param \ReflectionClass $rc 反射对象
     * @param string $cls 当前类名
     */
    private function analyzeClass(\ReflectionClass $rc, string $cls)
    {
        //Class注解
        $doc = $rc->getDocComment();

        //标识是否含有controller声明
        $controllerParam = '';

        //解析Class上的每个注解
        if ($doc != false && preg_match_all('/@(\w+)\((.*)\)/', $doc, $match))
        {
            foreach ($match[1] as $key => $item)
            {
                $param = trim(rtrim($match[2][$key], '/'), '"');
                switch ($item)
                {
                    case 'DefaultExceptionHandler':
                        $this->exceptionHandlerHolder['DefaultException'] = $cls;
                        break;
                    case 'ExceptionHandler':
                        $errName = substr($param, 0, strpos($param, ':'));
                        $this->exceptionHandlerHolder[$errName] = $cls;
                        break;
                    case 'Controller':
                        $controllerParam = rtrim($param, '/');
                        $this->controllerHolder[$param] = [$cls, 'index'];
                        break;
                    case 'Middleware':
                        $this->fillNamespace($cls, $param);
                        $this->middlewareHolder[$cls]['class'][] = $param;
                        break;
                    case 'GlobalMiddleware':
                        $this->globalMiddlewareParams[$cls] = $param;
                        $this->middlewareHolder['global'][] = $cls;
                        break;
                    case 'Service':
                        $name = '\\' . $rc->getInterfaceNames()[0];
                        $this->serviceHolder[$name] = $cls;
                        break;
                }
            }
        }

        //解析类下的每个方法
        $this->analyzeMethod($rc, $controllerParam, $cls);
    }

    /**
     * 解析控制器下的方法名，并加入路由中
     * @param \ReflectionClass $rc 当前反射对象
     * @param string $route 当前反射控制器的根路由
     * @param string $cls 当前反射对象的类名
     */
    private function analyzeMethod(\ReflectionClass $rc, string $route, string $cls)
    {
        foreach ($rc->getMethods() as $method)
        {
            // 排除特殊函数
            $name = $method->getName();
            if (strpos($name, '__') !== false)
            {
                continue;
            }

            //标识是否含有controller声明、绑定的View
            $controllerParam = null;
            $view = null;

            //解析方法上的注解
            $doc = $method->getDocComment();
            if ($doc != false && preg_match_all('/@(\w+)\((.*)\)/', $doc, $match))
            {
                foreach ($match[1] as $key => $item)
                {
                    $param = preg_replace('/["\'\s]/', '', rtrim($match[2][$key], '/'));
                    switch ($item)
                    {
                        case 'Controller':
                            $controllerParam = $param;
                            break;
                        case 'Middleware':
                            $this->fillNamespace($cls, $param);
                            $this->middlewareHolder[$cls][$name][] = $param;
                            break;
                        case 'Validate':
                            $this->handleValidate($cls, $name, $param);
                            break;
                        case 'TimerTask':
                            $timeout = $this->getTimeout($match);
                            $this->handleTimerTask($cls, $name, $param, $timeout);
                            break;
                        case 'Breaker':
                            $this->fillNamespace($cls, $param);
                            $this->breakerHolder[$cls][$name] = $param;
                            break;
                        case 'View':
                            $writeFile = true;
                            if ($this->workerId > 0)
                            {
                                $writeFile = false;
                            }
                            $view = $this->viewCompiler->build($param, $writeFile);
                            break;
                    }
                }
            }

            //判断是否有Controller，如果有，则需要重置路由地址
            if ($route != '')
            {
                //补充路由地址
                if ($controllerParam === null)
                {
                    $controllerParam = "$route/$name";
                }
                else
                {
                    $controllerParam = "$route/$controllerParam";
                }

                //双斜杠换成单斜杠并统一成小写
                $param = strtolower(str_replace('//', '/', $controllerParam));

                //处理用户简易路径
                preg_match_all('/(:(\w*))/', $param, $uris);
                if (count($uris[0]) > 0)
                {
                    //转换成表达式
                    $regex = preg_replace('/:(\w*)/', "(?'$1'\w+)", $param);
                    $regex = '/^' . str_replace('/', "\/", $regex) . '$/';

                    //放入表达式池
                    $this->uriMatchHolder[] = [
                        'target' => [$cls, $name, $view],
                        'param' => $uris[2],
                        'regex' => $regex,
                    ];
                }

                //加入路由控制器
                $this->controllerHolder[$param] = [$cls, $name, $view];

                //如果是index，则把View赋值给根页
                if ($name == 'index' && $view != null)
                {
                    $this->controllerHolder[$route] = [$cls, $name, $view];
                }
            }
        }
    }

    /**
     * 处理Controller数据校验器
     * @param string $cls 当前反射对象的类名
     * @param string $name 当前类的方法名
     * @param string $param Timer上的参数（时间间隔）
     */
    private function handleValidate(string $cls, string $name, string $param)
    {
        //分析参数
        $params = explode(',', $param);
        if (count($params) <= 1)
        {
            return;
        }

        //提取参数
        $col = $params[0];
        $fun = $params[1];
        $arg3 = $params[2] ?? '';
        $arg4 = $params[3] ?? '';

        //判断是什么请求与错误信息
        $method = 0;
        $message = '';
        if (preg_match('/Method::(Get|Post)/i', "$arg3$arg4", $match))
        {
            $message = str_replace($match[0], '', "$arg3$arg4");
            $method = ($match[1] == 'Get') ? 1 : 2;
        }
        else
        {
            $message = $arg3 . $arg4;
            $method = 1;
        }

        //分析调用方式
        $funs = explode('::', $fun);
        if (count($funs) != 2)
        {
            return;
        }

        //实现类
        $target = null;
        if($funs[0] == 'Validate')
        {
            $target = '\Swork\Server\Http\Validator';
        }
        else
        {
            $target = $funs[0] .':';
            $this->fillNamespace($cls, $target);
        }

        //组装规则
        $call = [
            'target' => $target,
            'void' => $funs[1],
            'method' => $method,
            'message' => $message,
        ];

        //两个字段比较的情况
        if(preg_match('/^(\w+)\|(\w+)$/', $col, $match))
        {
            $col = $match[1];
            $call['match'] = $match[2];
        }

        //Ins值范围的司
        if(preg_match('/^Ins\[([\w\|]+)\]$/', $funs[1], $match))
        {
            $call['void'] = 'Ins';
            $call['match'] = explode('|', $match[1]);
        }

        //Equal,Greater,Lesser值比较
        if(preg_match('/^(\w+)\[(\$?)(\w+)\]$/', $funs[1], $match))
        {
            $call['void'] = $match[1];
            $call['match'] = [$match[2], $match[3]];
        }

        //Length值长度
        if(preg_match('/^Length\[([0-9\-]+)\]$/', $funs[1], $match))
        {
            $call['void'] = 'Length';
            $call['match'] = explode('-', $match[1]);
        }

        //Range值范围
        if(preg_match('/^Range\[([0-9\-]+)\]$/', $funs[1], $match))
        {
            $call['void'] = 'Range';
            $call['match'] = explode('-', $match[1]);
        }

        //累加规则
        $this->validateHolder[$cls][$name][$col][] = $call;
    }

    /**
     * 处理定时器任务
     * @param string $cls 当前反射对象的类名
     * @param string $name 当前类的方法名
     * @param string $param Timer上的参数（时间间隔）
     * @param int $timeout 任务执行超时限制
     */
    private function handleTimerTask(string $cls, string $name, string $param, int $timeout)
    {
        if ($param == '')
        {
            return;
        }
        if (preg_match('/^\d+$/', $param))
        {
            $this->taskHolder['timer'][$param][] = [
                'cls' => $cls,
                'name' => $name,
                'timeout' => $timeout
            ];
            return;
        }
        if (preg_match('/^(\w+),(\d+)$/', $param, $match))
        {
            $this->taskHolder['timer'][$match[2]][] = [
                'cls' => $cls,
                'name' => $name,
                'group' => $match[1],
                'timeout' => $timeout
            ];
            return;
        }
        if (preg_match('/^(\w+)$/', $param, $match))
        {
            $this->taskHolder['salve'][$match[1]] = [
                'cls' => $cls,
                'name' => $name,
                'timeout' => $timeout
            ];
            return;
        }
    }

    /**
     * 获取超时配置节点内容
     * @param array $match
     * @return int
     */
    private function getTimeout(array $match)
    {
        foreach ($match[1] as $key => $item)
        {
            $param = preg_replace('/["\'\s]/', '', rtrim($match[2][$key], '/'));
            if ($item == 'Timeout')
            {
                return intval($param);
            }
        }
        return 10;
    }

    /**
     * 补全cls类的命令空间
     * @param string $cls 所有文件的类名
     * @param string $arg 需要补全的参数（输出补全的类）
     * @return mixed
     */
    private function fillNamespace($cls, &$arg)
    {
        $arg = substr($arg, 0, strpos($arg, ':'));
        if (substr($arg, 0, 1) != '\\')
        {
            $list = $this->usingHolder[$cls] ?? [];
            $ns = $list[$arg] ?? false;
            if ($ns == false)
            {
                $ns = $list['#'] . '\\' . $arg;
            }
            $arg = $ns;
        }
        return true;
    }
}
