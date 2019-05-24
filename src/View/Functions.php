<?php
namespace Swork\View;

use Swork\Exception\ViewException;

class Functions
{
    /**
     * 所有储存的方法
     * @var array
     */
    private $functions;

    /**
     * Compiler constructor.
     * @param array $functions 原始方法函数列表
     * @throws
     */
    public function __construct(array $functions)
    {
        $this->functions = $functions;
    }

    /**
     * 开始编译
     * @return array
     * @throws
     */
    public function explain()
    {
        //复制处理方法
        $functions = [];
        foreach ($this->functions as $hash => $function)
        {
            $functions[$hash] = $function;
        }

        //在新数组上处理
        foreach ($functions as $hash => $function)
        {
            //提取参数
            $type = $function['type'];
            $block = $function['block'];
            $text = $function['text'];

            //解析不同类型
            $return = 'null';
            switch ($type)
            {
                case 'block':
                    $return = $this->explain_block($text);
                    break;
                case 'assign':
                    $return = $this->explain_assign($block, $text);
                    break;
            }
            $this->functions[$hash]['return'] = $return;
        }

        //返回
        return $this->functions;
    }

    /**
     * 解析赋值部分
     * @param string $text 文本内容块
     * @return string
     * @throws
     */
    private function explain_block(string $text)
    {
        $res = [];
        $res[] = '$rel = [];';

        //分拆各个块
        $regex = '/\{(\/?\w+)\b([^\}]+)?\}/s';
        preg_match_all($regex, $text, $matches);
        $parts = preg_split($regex, $text);

        //循环解析每个块
        foreach ($matches[0] as $key => $item)
        {
            $block = $matches[1][$key];
            $param = $matches[2][$key];
            switch ($block)
            {
                case 'if':

                    //提取条件参数
                    if (!preg_match('/^([^=<>!]+)(([=<>!]+)(.+))?$/s', $param, $match))
                    {
                        continue;
                    }

                    //提取操作比较参数
                    $v1 = trim($match[1]);
                    $op = trim($match[3] ?? '');
                    $v2 = trim($match[4] ?? '');

                    //检查if条件是否有
                    if (empty($v1))
                    {
                        throw new ViewException('missing if condition');
                    }

                    //转换至代码运行级别
                    if (preg_match('/^\$(\w*)(.+)?$/', $v1, $match))
                    {
                        $v1 = $this->explain_value($match[1], ($match[2] ?? ''));
                    }
                    if (preg_match('/^\$(\w*)(.+)?$/', $v2, $match))
                    {
                        $v2 = $this->explain_value($match[1], ($match[2] ?? ''));
                    }

                    //加入if控制代码
                    $res[] = "if($v1 $op $v2)";
                    $res[] = "{";

                    //解析if后的代码
                    $ctxt = $parts[$key + 1];
                    $this->explain_text($res, $ctxt);

                    break;

                case 'else':
                    $res[] = "}else{";

                    //解析else后的代码
                    $ctxt = $parts[$key + 1];
                    $this->explain_text($res, $ctxt);

                    break;

                case '/if':
                case '/foreach':
                    $res[] = "}";

                    //解析/if后的代码
                    $ctxt = $parts[$key + 1];
                    $this->explain_text($res, $ctxt);

                    break;

                case 'foreach':

                    //提取属性参数
                    $properties = ViewHelper::getProperties($param);
                    $value = $properties['value'] ?? null;
                    $from = $properties['from'] ?? null;
                    $index = $properties['index'] ?? null;

                    //检查是否完整数据
                    if (empty($value))
                    {
                        throw new ViewException('missing foreach [value]');
                    }
                    if (empty($from))
                    {
                        throw new ViewException('missing foreach [from]');
                    }

                    //解析$from
                    if (preg_match('/\$(\w*)(\.[^\.]+)?$/', $from, $match))
                    {
                        $from = $this->explain_value($match[1], ($match[2] ?? ''));
                    }
                    else if (preg_match('/\$(\w+)\[(\d+)\]$/', $from, $match))
                    {
                        $from = '$vars[\'' . $match[1] . '\'][' . $match[2] . ']';
                    }

                    //加入foreach控制逻辑
                    if (empty($index))
                    {
                        $res[] = 'foreach (' . $from . ' as $' . $value . ')';
                    }
                    else
                    {
                        $res[] = 'foreach (' . $from . ' as $' . $index . ' => $' . $value . ')';
                    }
                    $res[] = "{";

                    //foreach变量赋值
                    $res[] = '$vars[\'' . $value . '\'] = $' . $value . ';';
                    if (!empty($index))
                    {
                        $res[] = '$vars[\'' . $index . '\'] = $' . $index . ';';
                    }

                    //解析foreach后的代码
                    $ctxt = $parts[$key + 1];
                    $this->explain_text($res, $ctxt);

                    break;
            }
        }

        //最后行
        $res[] = 'return join("\n", $rel);';

        //返回
        return join("\n", $res);
    }

    /**
     * 解析文本块（里面可能带有赋值的）
     * @param array $res 资源输出数组
     * @param string $text 文本块内容
     */
    private function explain_text(array &$res, string $text)
    {
        if (strlen(trim($text)) == 0)
        {
            return;
        }

        //清除单引号
        $text = ViewHelper::cleanQuoteText($text);

        //解析是否有赋值块
        preg_match_all('/\{\$(\w*)([^\}]+)?\}/s', $text, $matchs);
        foreach ($matchs[0] as $key => $item)
        {
            $val = $this->explain_value($matchs[1][$key], ($matchs[2][$key] ?? ''));
            $text = str_replace($item, '\'.' . $val . '.\'', $text);
        }

        //累加文本
        $res[] = '$rel[] = \'' . $text . '\';';
    }

    /**
     * 解析赋值部分
     * @param string $block 第一个字键名
     * @param string $text 文本内容块
     * @return string
     */
    private function explain_assign(string $block, string $text)
    {
        //解析文本值
        $rel = $this->explain_value($block, $text);

        //返回
        return "return $rel;";
    }

    /**
     * 解析赋值部分
     * @param string $block 第一个字键名
     * @param string $text 文本内容块（包括点开头的子数据 .）
     * @return string
     */
    private function explain_value(string $block, string $text)
    {
        $res = [];

        //提取额外方法
        if (preg_match('/\|(\w+)(:(.+))?\}/', $text, $ext))
        {
            $text = str_replace($ext[0], '}', $text);
        }

        //默认第一个提取值
        if (preg_match('/^(\w+)\[(\d+)\]$/', $block, $match))
        {
            $res[] = '$vars[\'' . $match[1] . '\'][' . $match[2] . ']';
        }
        else
        {
            $res[] = '$vars[\'' . $block . '\']';
        }

        //提取每一个键
        $rel = preg_match_all('/\.([^\.\}]*)/', $text, $matches);
        if ($rel > 0)
        {
            foreach ($matches[1] as $key => $item)
            {
                if (preg_match('/^(\w+)\[(\d+)\]$/', $item, $idx))
                {
                    $res[] = '[\'' . $idx[1] . '\'][' . $idx[2] . ']';
                }
                else
                {
                    $res[] = '[\'' . $item . '\']';
                }
            }
        }

        //合成运行级的代码
        $val = join('', $res);

        //如果有额外方法
        if ($ext)
        {
            $plugin = $this->getPluginInstance($ext[1]);
            $val = $plugin->execute($val, ($ext[3] ?? ''));
        }

        //返回
        return $val;
    }

    /**
     * 获取方法插件
     * @param string $plugin
     * @return PluginInterface
     */
    private function getPluginInstance(string $plugin): PluginInterface
    {
        $cls = '\Swork\View\Plugin\\' . ucfirst($plugin) . 'Plugin';
        return new $cls();
    }
}