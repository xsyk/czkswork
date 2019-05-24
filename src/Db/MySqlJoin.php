<?php
namespace Swork\Db;

use Swork\Client\MySql;

class MySqlJoin extends MySql
{
    /**
     * 左边对象
     * @var MySqlModel
     */
    private $leftModel;

    /**
     * 已经连接的JOIN条件
     * @var array
     */
    private $joins;

    /**
     * 联合表简写符号
     * @var array
     */
    private static $shorts = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

    /**
     * MySqlJoin constructor.
     * @param MySqlModel $leftModel 左边对象
     * @param MySqlModel $rightModel 右边对象
     * @param array $on 联合表条件
     * @param array $joins 已经连接的表
     */
    public function __construct(MySqlModel $leftModel, MySqlModel $rightModel, array $on, array $joins = [])
    {
        $this->leftModel = $leftModel;
        $this->joins = $joins;
        $this->joins[] = [$leftModel, $rightModel, $on];
    }

    /**
     * 联合表查询
     * @param MySqlModel $model 右表对象
     * @param array $on 联合条件，['左表字段' => '右表字段']
     * @return MySqlJoin
     */
    function join(MySqlModel $model, array $on): MySqlJoin
    {
        return new MySqlJoin($this->leftModel, $model, $on, $this->joins);
    }

    /**
     * 联合表查询（左联）
     * @param MySqlModel $model 右表对象
     * @param array $on 联合条件，['左表字段' => '右表字段']
     * @return MySqlJoin
     */
    function leftJoin(MySqlModel $model, array $on): MySqlJoin
    {
        $on['_'] = 'LEFT';
        return new MySqlJoin($this->leftModel, $model, $on, $this->joins);
    }

    /**
     * 获取列表数据
     * @param array $where 数据条件（按顺序A表，B表联合，默认A表） ['A.id' => 123, 'B.name' => 'xixi', '$or' => [ id=>5, age => 9]]
     * @param string $cols 输出的字段，使用逗号分隔
     * @param array $order 排序 ['odr' => 1, 'atime' => -1]
     * @param int $size 输出数量，0表示全部
     * @param int $idx 页码位置，从1开始，0表示不使用翻页
     * @return array
     * @throws
     */
    function getList(array $where = [], string $cols = '*', array $order = [], $size = 0, int $idx = 0)
    {
        //联合表字段
        $items = $this->getJoinTableColumns();

        //分析数据条件
        $query = new MySqlQuery($items, $where, $order);
        $condition = $query->getCondition();
        $orderby = $query->getOrderBy();

        //合并字符
        if (empty($cols) || $cols == '*')
        {
            $cols = join(',', array_keys($items));
        }

        //合成SQL
        $sql = "SELECT $cols FROM `{$this->leftModel->getTbl()}` A";
        $sql .= $this->getJoinOnCondition();
        $sql .= $condition['where'];

        //排序
        if (!empty($orderby))
        {
            $sql .= " ORDER BY $orderby";
        }

        //数量
        if ($size > 0)
        {
            if ($idx > 0)
            {
                $offset = $size * ($idx - 1);
                $sql .= " LIMIT $offset,$size";
            }
            else
            {
                $sql .= " LIMIT $size";
            }
        }
        $sql .= ';';

        //执行
        $this->getCollector($this->leftModel->getNode());
        $result = $this->execute($sql, $condition['types'], $condition['values'], true);

        //获取结果，并转化为相应字段类型
        $list = $result['Results'];
        if (count($condition['types']) == 0)
        {
            $this->forceToDataType($list);
        }

        //返回结果
        return $list ?: [];
    }

    /**
     * 通过条件获取一行数据
     * @param array $where 数据条件 ['id' => 123, 'name' => 'xixi', '$or' => [ id=>5, age => 9]]
     * @param string $cols 输出的字段，使用逗号分隔
     * @param array $order 排序 ['odr' => 1, 'atime' => -1]
     * @return bool|array
     * @throws
     */
    public function getRow(array $where = [], string $cols = '*', array $order = [])
    {
        $result = $this->getList($where, $cols, $order, 1);
        if (count($result) > 0)
        {
            return $result[0];
        }
        return false;
    }

    /**
     * 获取数量
     * @param array $where 数据条件 ['id' => 123, 'name' => 'xixi', '$or' => [ id => 5, age => 9]]
     * @param string $cols 输出的字段，默认是*，或 distinct(uid)，只能一个字段
     * @return int
     * @throws
     */
    function getCount(array $where = [], string $cols = '*')
    {
        //联合表字段
        $items = $this->getJoinTableColumns();

        //分析数据条件
        $query = new MySqlQuery($items, $where, []);
        $condition = $query->getCondition();

        //合成SQL
        $sql = "SELECT COUNT($cols) FROM `{$this->leftModel->getTbl()}` A";
        $sql .= $this->getJoinOnCondition();
        $sql .= $condition['where'];

        //执行
        $this->getCollector($this->leftModel->getNode());
        $result = $this->execute($sql, $condition['types'], $condition['values'], true);

        //返回结果
        if ($result != false && count($result['Results']) > 0)
        {
            return (int)array_values($result['Results'][0])[0];
        }
        return 0;
    }

    /**
     * 获取联合表字段列表
     * @return array
     */
    private function getJoinTableColumns()
    {
        //联合表字段
        $list = [];

        //合并最左边表的字段
        foreach ($this->leftModel->getCols() as $name => $item)
        {
            $list[self::$shorts[0] . '.' . $name] = $item;
        }

        //合并其它联合表的字段
        foreach ($this->joins as $idx => $join)
        {
            foreach ($join[1]->getCols() as $name => $item)
            {
                $list[self::$shorts[$idx + 1] . '.' . $name] = $item;
            }
        }
        return $list;
    }

    /**
     * 获取联合条件SQL部分
     * @return string
     */
    private function getJoinOnCondition()
    {
        $list = [];
        foreach ($this->joins as $idx => $join)
        {
            $tbl = $join[1]->getTbl();
            $left = array_keys($join[2])[0];
            $short = self::$shorts[$idx + 1];
            $type = $join[2]['_'] ?? '';
            $list[] = " $type JOIN `$tbl` $short ON A.`{$left}`=$short.`{$join[2][$left]}`";
        }
        return join('', $list);
    }

    /**
     * 强制转换至字段应有的数据类型
     * @param array $list
     */
    private function forceToDataType(&$list)
    {
        if ($list == false)
        {
            return;
        }
        foreach ($list as $key => $item)
        {
            if (is_int($key))
            {
                foreach ($item as $col => $val)
                {
                    $list[$key][$col] = $this->convertToDataType($col, $val);
                }
            }
            else
            {
                $list[$key] = $this->convertToDataType($key, $item);
            }
        }
    }

    /**
     * 根据字义的数据类型转换成相应的数据类型
     * @param string $col 字段名
     * @param mixed $val 字段值
     * @return float|int
     */
    private function convertToDataType(string $col, $val)
    {
        $type = $this->cols[$col][0] ?? false;
        if ($type == false)
        {
            return $val;
        }
        if ($type == 'i')
        {
            return intval($val);
        }
        if ($type == 'd')
        {
            return floatval($val);
        }
        return $val;
    }
}
