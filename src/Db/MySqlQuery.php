<?php
namespace Swork\Db;

class MySqlQuery
{
    /**
     * 支持的连接符
     */
    const CONNECTORS = [
        '$OR' => 1,
        '$AND' => 2,
        '$GROUP' => 3,
        '$INDEX' => 4,
    ];

    /**
     * 支持的操作符
     */
    const OPERATORS = [
        '>' => 0,
        '<' => 0,
        '>=' => 0,
        '<=' => 0,
        'IN' => 0,
        '=' => 0,
        '!=' => 0,
        'LIKE' => 0,
        'NOT LIKE' => 0,
        'NOT IN' => 0,
        'BETWEEN' => 0,
        'NOT BETWEEN' => 0,
    ];

    /**
     * 连接符
     */
    const OP_AND = ' AND ';
    const OP_OR = ' OR ';

    /**
     * 外部参数
     * @var array
     */
    private $_cols = null;
    private $_where = null;
    private $_order = null;

    /**
     * 字符储存容器
     * @var array
     */
    private $sql = [];
    private $values = [];
    private $types = [];

    /**
     * 当前操作连接（and, or）
     * @var string
     */
    private $currentOP = null;

    /**
     * force index 强制索引
     * @var string
     */
    private $index = null;

    /**
     * group by 字段
     * @var string
     */
    private $group = null;

    /**
     * MySqlQuery constructor.
     * @param array $cols
     * @param array $where
     * @param array $order
     */
    public function __construct(array $cols, array $where, array $order)
    {
        $this->_cols = $cols;
        $this->_where = $where;
        $this->_order = $order;
    }

    /**
     * 获取SQL数据条件
     * @return array
     */
    public function getCondition()
    {
        /**
         * { id => 1 } : where id=1
         * { id => 1, name => xx } : where id=1 and name=xx
         * { id => 1, name => ['like' => '%a%'] } : where id=1 and name like '%a%'
         * { id => 1, age => {'>' => 10} } : where id=1 and age > 10
         * { id => 1, sex => {'in' => [1,2]} } : where id=1 and sex in (1,2)
         * { id => 1, age => {'>' => 10, '<' => 100} } : where id=1 and age > 10 and age < 100
         * { id => 1, '$or' => [ {age => {'<' => 10}}, {age => {'>' => 100} } ] } : where id=1 and (age < 10 or age > 100)
         * { id => 1, '$or' => [ {age => {'<' => 10}}, {'$and' => [{age => {'>' => 100}}, {sex => 1}] ] } : where id=1 and (age < 10 or (age > 100 and sex=1))
         * { id => 1, '$group' => [ age, sex ] } : where id=1 group by age,sex
         * { '$or' => [{ id => 1}, {name => xx }]} : where id=1 or name=xx
         * { '$or' => [{ id => 1}, {name => xx }], sex => 1} : where (id=1 or name=xx) and sex=1
         * { '$index' => 'idx1,idx2'} : select * from T FORCE INDEX (idx1,idx2) where ......
         */

        //如果不为空值
        if (!empty($this->_where))
        {
            $this->currentOP = MySqlQuery::OP_AND;
            $this->explain($this->_where);
        }

        //合并SQL
        $where = null;
        if (count($this->sql) > 0)
        {
            $where .= ' WHERE ' . join('', $this->sql);
        }
        if ($this->group != false)
        {
            $where .= ' GROUP BY ' . $this->group;
        }

        //返回
        return [
            'where' => $where,
            'index' => $this->index,
            'types' => $this->types,
            'values' => $this->values,
        ];
    }

    /**
     * 把？号替换至真实的数值
     * @return mixed|string
     */
    public function getTransform()
    {
        $condition = $this->getCondition();
        $where = $condition['where'];
        $types = $condition['types'];
        $values = $condition['values'];

        //如果没有？号
        if (count($this->types) == 0)
        {
            return $where;
        }

        //替换？号
        $sql = [];
        foreach (explode('?', $where) as $key => $item)
        {
            if ($key > 0)
            {
                $type = $types[$key - 1];
                if ($type == 'i' || $types == 'd')
                {
                    $sql[] = $values[$key - 1];
                }
                else
                {
                    $sql[] = '\'' . $values[$key - 1] . '\'';
                }
            }
            $sql[] = $item;
        }

        //合并返回
        return join('', $sql);
    }

    /**
     * 获取排序语句
     * @return null|string
     */
    public function getOrderBy()
    {
        if (empty($this->_order))
        {
            return null;
        }

        $orderby = [];
        foreach ($this->_order as $key => $value)
        {
            $orderby[] = $key . ' ' . ($value == 1 ? 'ASC' : 'DESC');
        }

        return join(',', $orderby);
    }

    /**
     * 解析数据条件
     * @param array $where
     */
    private function explain(array $where)
    {
        foreach ($where as $key => $val)
        {
            $key = trim($key);
            $idx = strtoupper($key);
            if (isset(MySqlQuery::CONNECTORS[$idx]))
            {
                switch ($idx)
                {
                    case '$OR':
                    case '$AND':
                        $tmpOP = $this->currentOP;
                        $this->pushCurrentOP();
                        $this->currentOP = ($idx == '$OR') ? MySqlQuery::OP_OR : MySqlQuery::OP_AND;
                        $this->sql[] = "(";

                        foreach ($val as $nm => $va)
                        {
                            $this->explain($va);
                        }

                        $this->sql[] = ")";
                        $this->currentOP = $tmpOP;
                        break;
                    case '$INDEX':
                        $this->index = " FORCE INDEX ($val)";
                        break;
                    case '$GROUP':
                        $this->group = is_array($val) ? join(',', $val) : $val;
                        break;
                }
            }
            else
            {
                if (is_array($val))
                {
                    foreach ($val as $op => $va)
                    {
                        $op = trim($op);
                        $op = strtoupper($op);
                        switch ($op)
                        {
                            case '>':
                            case '>=':
                            case '<':
                            case '<=':
                            case '=':
                            case '!=':
                            case 'LIKE':
                            case 'NOT LIKE':
                                $this->pushCurrentOP();
                                $this->sql[] = "$key $op ?";
                                $this->types[] = $this->getType($key);
                                $this->values[] = $va;
                                break;
                            case 'IN':
                            case 'NOT IN':
                                $this->pushCurrentOP();
                                if (count($va) == 1)
                                {
                                    $this->sql[] = $key . ($op == 'IN' ? '=?' : '!=?');
                                    $this->types[] = $this->getType($key);
                                    $this->values[] = reset($va); // 用reset代替$va[0]，兼容关联数组
                                }
                                else
                                {
                                    $marks = [];
                                    foreach ($va as $v)
                                    {
                                        $marks[] = '?';
                                        $this->types[] = $this->getType($key);
                                        $this->values[] = $v;
                                    }
                                    $marks = join(',', $marks);
                                    $this->sql[] = "$key $op ($marks)";
                                }
                                break;
                            case 'BETWEEN':
                            case 'NOT BETWEEN':
                                $this->pushCurrentOP();
                                $this->sql[] = "$key $op ? AND ?";
                                $this->types[] = $this->getType($key);
                                $this->types[] = $this->getType($key);
                                $this->values[] = $va[0];
                                $this->values[] = $va[1];
                                break;
                        }

                    }
                }
                else
                {
                    $this->pushCurrentOP();
                    $this->sql[] = "$key = ?";
                    $this->types[] = $this->getType($key);
                    $this->values[] = $val;
                }
            }
        }
    }

    /**
     * 获取字段类型
     * @param string $key 字段名称
     * @return string
     */
    private function getType(string $key)
    {
        return $this->_cols[$key][0];
    }

    private function pushCurrentOP()
    {
        if (count($this->sql) > 0 && end($this->sql) != '(')
        {
            $this->sql[] = $this->currentOP;
        }
    }
}
