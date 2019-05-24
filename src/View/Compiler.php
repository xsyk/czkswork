<?php
namespace Swork\View;

use Swork\Configer;

class Compiler
{
    private $sep;

    /**
     * 储存视图文件的根目录
     * @var string
     */
    private $viewfolder;

    /**
     * 编译成HTML的文件夹
     * @var string
     */
    private $htmlfolder;

    /**
     * 所有储存的方法
     * @var array
     */
    private $functions;

    /**
     * Compiler constructor.
     * @param array $env 环境配置
     * @throws
     */
    public function __construct(array $env)
    {
        //全局参数
        $root = $env['root'];
        $sep = $this->sep = $env['sep'];

        //视图文件根目录
        $viewfolder = Configer::get('frame:view_dir', 'app/View');
        $viewfolder = ViewHelper::filterSeparator($viewfolder);
        $viewfolder = $root . $viewfolder . $sep;

        //视图tpl路径、编译目标文件夹
        $htmlfolder = $root . 'runtime' . $sep . 'html' . $sep;

        //如果编译目录不存在，则创建
        if (!file_exists($htmlfolder))
        {
            @mkdir($htmlfolder, 0777, true);
        }

        //赋值
        $this->viewfolder = $viewfolder;
        $this->htmlfolder = $htmlfolder;
    }

    /**
     * 编译模板
     * @param string $viewfile 视图文件（不含根目录）
     * @param bool $writeFile 是否写入编译后的文件（用于区分多线程启动，省掉子线程再次编译）
     * @return array 返回编译后文件路径，编译失败返回false
     * @throws
     */
    public function build(string $viewfile, bool $writeFile = true)
    {
        //视图文件
        $viewfile = ViewHelper::filterSeparator($viewfile);
        $filehash = md5($viewfile);

        //检查视图文件是否存在
        $filename = $this->viewfolder . $viewfile;
        if (!file_exists($filename))
        {
            return null;
        }

        //获取命名空间配置
        $ns_prefix = Configer::get('frame:ns_prefix', 'App\\');
        $ns_prefix = explode('\\', $ns_prefix);
        $namespace = "{$ns_prefix[0]}\\runtime\\html";

        //编译后的文件名和类实例名
        $clsFile = $this->htmlfolder . $filehash . '.php';
        $clsName = "\\$namespace\\Tpl_$filehash";

        //如果不需要编译
        if ($writeFile == false)
        {
            return [$clsFile, $clsName];
        }

        //读取文件内容
        $content = file_get_contents($filename);

        //编译Include
        $filepath = dirname($filename) . $this->sep;
        $content = $this->complie_include($filepath, $content);

        //编译功能块
        $content = $this->complie_block($content);
        $content = $this->complie_assign($content);

        //单引号转义
        $content = str_replace('\'', '\\\'', $content);

        //方法替换
        $content = preg_replace('/\[(func_[^\]]+)\]/', '\'.$this->\\1.\'', $content);

        //转化成文本
        $tpls[] = '<?php';
        $tpls[] = "namespace $namespace;";

        $tpls[] = '';
        $tpls[] = "class Tpl_$filehash {";

        //自定义方法部分
        $funs_cls = new Functions($this->functions);
        $func_list = $funs_cls->explain();
        foreach ($func_list as $hash => $func)
        {
            $tpls[] = 'private function func_' . $hash . '(array $vars)';
            $tpls[] = '{';
            $tpls[] = '    ' . $func['return'] . '';
            $tpls[] = '}';
            $tpls[] = '';
        }

        //内容部分
        $tpls[] = 'public function render(array $vars)';
        $tpls[] = '{';
        $tpls[] = "    return '$content';";
        $tpls[] = '}';
        $tpls[] = "}";

        //写入文件
        file_put_contents($clsFile, join("\n", $tpls));

        //返回（类文件名与类实例名）
        return [$clsFile, $clsName];
    }

    /**
     * 编译Include文件
     * @param string $filepath
     * @param string $content
     * @return mixed|string
     */
    private function complie_include(string $filepath, string $content)
    {
        //提取Include
        $rel = preg_match_all('/\{include([^}]+)\}/', $content, $match);
        if ($rel == false)
        {
            return $content;
        }

        //提取文件夹合集
        $path_folders = ViewHelper::getPathFolders($filepath);

        //解析每个Include
        foreach ($match[1] as $key => $item)
        {
            //获取属性内容
            $properties = ViewHelper::getProperties($item);

            //加载包含文件名
            $ifile = $properties['file'] ?? '';
            if ($ifile == '')
            {
                continue;
            }

            //判断是否上层目录
            $ifiles = explode('../', $ifile);
            $ups = count($ifiles);
            if ($ups > 1)
            {
                $ifolders = array_slice($path_folders, 0, count($path_folders) - $ups + 1);
                $filename = $this->sep . join($this->sep, $ifolders) . $this->sep . $ifiles[$ups - 1];
            }
            else
            {
                $filename = $filepath . $ifile;
            }

            //判断文件是否存在
            if (!file_exists($filename))
            {
                continue;
            }

            //读取文件内容并
            $filetext = file_get_contents($filename);

            //递归编译Include
            $nextpath = dirname($filename) . $this->sep;
            $filetext = $this->complie_include($nextpath, $filetext);

            //替换Include
            $content = str_replace($match[0][$key], $filetext, $content);
        }

        //返回
        return $content;
    }

    /**
     * 编译功能块
     * @param string $content
     * @return mixed|string
     */
    private function complie_block(string $content)
    {
        //提取功能块
        preg_match_all('/\{([\w]+)([^\}]+)\}(((.*?)|(?R))*)\{\/\\1\}/s', $content, $match);
        foreach ($match[0] as $key => $item)
        {
            //块名
            $block = $match[1][$key];

            //找到几个开口和闭口
            $opens = preg_match_all('/\{' . $block . '/', $item);
            $closes = preg_match_all('/\/' . $block . '\}/', $item);
            $diffs = $opens - $closes;

            //如果在差额
            if ($diffs > 0)
            {
                $field = '{/' . $block . '}';
                $step = strlen($field);

                //初始位置
                $start = strpos($content, $item);
                $next = $start + strlen($item);
                while ($diffs > 0)
                {
                    $pos = strpos($content, $field, $next);
                    $next = $pos + $step;
                    $diffs--;
                }

                //新的块内容
                $item = substr($content, $start, $next - $start);
            }

            //替换自定义方法来处理
            $hash = md5($item);
            $func = '[func_' . $hash . '($vars)]';
            $content = str_replace($item, $func, $content);

            //加入自定义方法中
            $this->functions[$hash] = [
                'type' => 'block',
                'block' => $block,
                'text' => $item
            ];
        }

        //返回
        return $content;
    }

    /**
     * 编译赋值块
     * @param string $content
     * @return mixed|string
     */
    private function complie_assign(string $content)
    {
        //提取功能块
        preg_match_all('/\{\$(\w*(\[\d+\])?)([^\}]+)?\}/sm', $content, $match);
        foreach ($match[0] as $key => $item)
        {
            //块名
            $block = $match[1][$key];

            //替换自定义方法来处理
            $hash = md5($item);
            $func = '[func_' . $hash . '($vars)]';
            $content = str_replace($item, $func, $content);

            //加入自定义方法中
            $this->functions[$hash] = [
                'type' => 'assign',
                'block' => $block,
                'text' => $item,
            ];
        }

        //返回
        return $content;
    }
}