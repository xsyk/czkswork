<?php
namespace Swork\Model;

use Swork\Client\MySql;
use Swork\Configer;
use Swork\Initialize;

class Generator
{
    /**
     * @var array
     */
    private $env;

    /**
     * 命名空间前缀
     * @var string
     */
    private $nsPrefix;

    /**
     * @var MySql
     */
    private $mysql;

    /**
     * 后缀名
     * @var string
     */
    private $suffix = 'Model';

    public function __construct(array $env)
    {
        $this->env = $env;

        //载入命名空间前缀
        $this->nsPrefix = Configer::get('frame:ns_prefix', 'App\\');

        //初始化数据库连接池
        $init = new Initialize();
        $init->db();

        //初始化MySQL客户端
        $this->mysql = new MySql();
    }

    function create(array $opt)
    {
        $inc = isset($opt['i']) ? $opt['i'] : '';
        $prefix = isset($opt['prefix']) ? $opt['prefix'] : '';
        $folder = isset($opt['folder']) ? $opt['folder'] : '';
        $sep = $this->env['sep'];
        $path = $this->env['root'] . 'app' . $sep . 'Model' . $sep;
        if ($folder != '')
        {
            $path .= $folder . $sep;
        }

        //获取模板内容
        $tpl = file_get_contents(dirname(__FILE__) . $sep . 'Template.tpl');

        echo 'Load template done' . PHP_EOL;

        //获取表名
        $tbls = $this->loadTables($inc);
        foreach ($tbls as $tbl)
        {
            $name = array_values($tbl)[0];
            $idName = '';
            $idType = '';
            $columns = [];

            echo 'Processing table [' . $name . ']' . PHP_EOL;

            //获取表结构
            $cols = $this->loadColumns($inc, $name);
            foreach ($cols as $col)
            {
                $field = $col['Field'];
                $type = $col['Type'];
                $key = $col['Key'];
                $extra = $col['Extra'];
                $default = $col['Default'] ?? '';
                if ($default == null)
                {
                    $default = '';
                }

                //主键
                if ($idName == '' && strtolower($key) == 'pri')
                {
                    $idName = $field;
                    $idType = 'KeyID';
                    if (strtolower($extra) == 'auto_increment')
                    {
                        $idType = 'AutoKeyID';
                    }
                }

                //字段
                $columns[] = [
                    'field' => $field,
                    'default' => $this->defaultValue($type, $default)
                ];
            }

            //类名
            $className = $this->className($name, $prefix);

            //生成文件
            $this->createFile($inc, $tpl, $path, $folder, $className, $name, $idName, $idType, $columns);
        }

        echo 'Generate done' . PHP_EOL;
    }

    function loadTables(string $inc)
    {
        return $this->mysql->query('show tables', $inc);
    }

    function loadColumns(string $inc, string $tbl)
    {
        return $this->mysql->query("desc $tbl", $inc);
    }

    function defaultValue(string $type, string $default)
    {
        $val = null;
        $type = strtolower(preg_replace('/[\s\(].*/', '', $type));
        switch ($type)
        {
            case 'int':
            case 'bool':
            case 'tinyint':
            case 'mediumint':
            case 'smallint':
                $val = '[\'i\', ' . intval($default) . ']';
                break;
            case 'float':
            case 'number':
            case 'decimal':
                $val = '[\'d\', ' . floatval($default) . ']';
                break;
            case 'json':
                $val = '[\'s\', \'' . ($default ?: '[]') . '\']';
                break;
            default:
                $val = '[\'s\', \'' . trim(json_encode($default), '"') . '\']';
                break;
        }
        return $val;
    }

    function className(string $tbl, $prefix)
    {
        if ($prefix != '')
        {
            $tbl = ltrim($tbl, $prefix);
        }
        $tbl = ucwords(str_replace(['-', '_'], ' ', $tbl));
        $tbl = str_replace(' ', '', $tbl);
        return $tbl . $this->suffix;
    }

    function createFile(string $inc, string $tpl, string $path, string $folderName, string $className, string $tblName, string $idName, string $idType, array $columns)
    {
        //生成字段
        $cols = [];
        foreach ($columns as $column)
        {
            $field = $column['field'];
            $default = $column['default'];
            $cols[] = "'$field' => $default";
        }

        //创建文件夹
        if (!file_exists($path))
        {
            mkdir($path, 0777, true);
        }

        //处理子文件夹作为命令空间的
        if ($folderName != '')
        {
            $folderName = '\\' . $folderName;
        }

        //替换内容
        $text = str_replace('{{NSPrefix}}', $this->nsPrefix, $tpl);
        $text = str_replace('{{FolderName}}', $folderName, $text);
        $text = str_replace('{{ClassName}}', $className, $text);
        $text = str_replace('{{TableName}}', $tblName, $text);
        $text = str_replace('{{IdName}}', $idName, $text);
        $text = str_replace('{{IdType}}', $idType, $text);
        $text = str_replace('{{Columns}}', join(",\n            ", $cols), $text);
        $text = str_replace('{{Instance}}', $inc, $text);

        //写入文件
        $file = $path . $className . '.php';
        file_put_contents($file, $text);
    }
}
