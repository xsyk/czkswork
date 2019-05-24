<?php
namespace Swork\Db;

use Swork\Bean\Holder\InstanceHolder;
use Swork\Client\MySql;
use Swork\Exception\DbException;

class MySqlModel extends MySql
{
    /**
     * 自增主键
     */
    const AutoKeyID = 1;

    /**
     * 自增主键
     */
    const KeyID = 2;

    /**
     * 表名
     * @var string
     */
    private $tbl;

    /**
     * 主键信息（哪个是主键和主键类型）
     * @var array
     */
    private $key;

    /**
     * 字段结构（声明默认值）
     * @var array
     */
    private $cols;

    /**
     * 默认对象
     * @var mixed
     */
    private $default;

    /**
     * 连接节点
     * @var string
     */
    private $node;

    /**
     * 当前实例化对象
     * @var MySqlModel
     */
    private static $instace = null;

    /**
     * 初始表结构
     * @param string $tbl 表名
     * @param array $key 主键信息（哪个是主键和主键类型）
     * @param array $cols 字段结构（声明默认值）
     * @param string $node 使用连接数据库节点
     * @throws
     */
    public function __construct(string $tbl, array $key, array $cols, string $node)
    {
        $this->tbl = $tbl;
        $this->key = $key;
        $this->cols = $cols;
        $this->node = $node;
        $this->getCollector($node);
    }

    /**
     * 单件实列化
     * @return MySqlModel
     */
    public static function M()
    {
        $class = static::class;
        if (empty(self::$instace[$class]))
        {
            self::$instace[$class] = InstanceHolder::getClass($class);
        }
        return self::$instace[$class];
    }

    /**
     * 执行原始SQL语句
     * @param string $sql 待执行的SQL
     * @param bool $read 是否为使用只读连接池
     * @return mixed
     * @throws
     */
    public function doQuery(string $sql, bool $read = false)
    {
        return $this->execute($sql, [], [], $read);
    }

    /**
     * 执行原始的SQL语句（用于列表）
     * @param string $sql 待执行的SQL
     * @param MySqlQuery $query MySQL查询器
     * @param int $size 输出数量，0表示全部
     * @param int $idx 页码位置，从1开始，0表示不使用翻页
     * @return array
     * @throws
     */
    public function doList(string $sql, MySqlQuery $query, $size = 0, int $idx = 0)
    {
        //分析数据条件
        $condition = $query->getCondition();
        $orderby = $query->getOrderBy();

        //合并字符
        $sql = "$sql {$condition['where']}";
        if (!empty($orderby))
        {
            $sql .= " ORDER BY $orderby";
        }
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
        $result = $this->execute($sql, $condition['types'], $condition['values'], true);

        //获取结果，并转化为相应字段类型
        $list = $result['Results'];
        if (count($condition['types']) == 0)
        {
            $this->forceToDataType($list);
        }

        //返回
        return $list ?: [];
    }

    /**
     * 获取表名
     * @return string
     */
    public function getTbl()
    {
        return $this->tbl;
    }

    /**
     * 获取字段列表
     * @return array
     */
    public function getCols()
    {
        return $this->cols;
    }

    /**
     * 获取主键
     * @return array
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * 连接实例名
     * @return string
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * 获取默认对象
     * @return array
     */
    public function getDefault()
    {
        if ($this->default != null)
        {
            return $this->default;
        }
        $this->default = [];
        foreach ($this->cols as $col => $val)
        {
            $this->default[$col] = $val[1];
        }
        return $this->default;
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
     * 通过主键值获取一行数据
     * @param mixed $id 主键值
     * @param string $cols $cols 输出的字段，使用逗号分隔
     * @param array $order 排序 ['odr' => 1, 'atime' => -1]
     * @return bool|mixed
     * @throws
     */
    public function getRowById($id, string $cols = '*', array $order = [])
    {
        $where = $this->getIdCondition($id);
        $result = $this->getList($where, $cols, $order, 1);
        if (count($result) > 0)
        {
            return $result[0];
        }
        return false;
    }

    /**
     * 通过条件获取一个数据
     * @param array $where 数据条件 ['id' => 123, 'name' => 'xixi', '$or' => [ id=>5, age => 9]]
     * @param string $cols 输出的字段，使用逗号分隔，*号则取主键
     * @param array $order 排序 ['odr' => 1, 'atime' => -1]
     * @param mixed $dft 默认值（指定字段记录不存在时返回）
     * @return bool|string
     * @throws
     */
    public function getOne(array $where = [], string $cols = '*', array $order = [], $dft = false)
    {
        $cols = $cols == '*' ? $this->key[0] : $cols;
        $result = $this->getRow($where, $cols, $order);
        if ($result != false && isset($result[$cols]))
        {
            return $result[$cols];
        }
        return $dft;
    }

    /**
     * 通过主键值获取一个数据
     * @param mixed $id 主键值
     * @param string $cols 输出的字段，使用逗号分隔，*号则取主键
     * @param array $order 排序 ['odr' => 1, 'atime' => -1]
     * @param mixed $dft 默认值（指定字段记录不存在时返回）
     * @return bool|string
     * @throws
     */
    public function getOneById($id, string $cols = '*', array $order = [], $dft = false)
    {
        $where = $this->getIdCondition($id);
        return $this->getOne($where, $cols, $order, $dft);
    }

    /**
     * 获取列表数据
     * @param array $where 数据条件 ['id' => 123, 'name' => 'xixi', '$or' => [ id=>5, age => 9]]
     * @param string $cols 输出的字段，使用逗号分隔
     * @param array $order 排序 ['odr' => 1, 'atime' => -1]
     * @param int $size 输出数量，0表示全部
     * @param int $idx 页码位置，从1开始，0表示不使用翻页
     * @return array
     * @throws
     */
    function getList(array $where = [], string $cols = '*', array $order = [], $size = 0, int $idx = 0)
    {
        //安全判断
        if (empty($cols))
        {
            $cols = '*';
        }

        //分析数据条件
        $query = new MySqlQuery($this->cols, $where, $order);
        $condition = $query->getCondition();
        $orderby = $query->getOrderBy();

        //合并字符
        $sql = "SELECT $cols FROM `{$this->tbl}`{$condition['index']} {$condition['where']}";
        if (!empty($orderby))
        {
            $sql .= " ORDER BY $orderby";
        }
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
     * 获取数量
     * @param array $where 数据条件 ['id' => 123, 'name' => 'xixi', '$or' => [ id => 5, age => 9]]
     * @param string $cols 输出的字段，默认是*，或 distinct(uid)，只能一个字段
     * @return int
     * @throws
     */
    function getCount(array $where = [], string $cols = '*')
    {
        //分析数据条件
        $query = new MySqlQuery($this->cols, $where, []);
        $condition = $query->getCondition();

        //合并字符
        $sql = "SELECT COUNT($cols) FROM `{$this->tbl}` {$condition['where']};";

        //执行
        $result = $this->execute($sql, $condition['types'], $condition['values'], true);

        //返回结果
        if ($result != false && count($result['Results']) > 0)
        {
            return (int)array_values($result['Results'][0])[0];
        }
        return 0;
    }

    /**
     * 获取数据字典对象 key-info 格式（仅能用于少量数据的情况，不推荐在大量数据是使用）
     * @param string $key 字典的KEY
     * @param array $where 数据条件 ['id' => 123, 'name' => 'xixi', '$or' => [ id => 5, age => 9]]
     * @param string $cols 需要输出的字段，逗号分隔
     * @return array
     * @throws
     */
    function getDict(string $key, array $where = [], $cols = '*')
    {
        //补充where语句和字段
        if (trim($cols) != '*' && strpos($cols, $key) === false)
        {
            $cols .= ',' . $key;
        }

        //获取数据
        $list = $this->getList($where, $cols);

        //返回
        return $list == false ? [] : array_column($list, null, $key);
    }

    /**
     * 获取数据字典列表 key-list 格式（仅能用于少量数据的情况，不推荐在大量数据是使用）
     * @param string $key 字典的KEY
     * @param array $where 数据条件 ['id' => 123, 'name' => 'xixi', '$or' => [ id => 5, age => 9]]
     * @param string $cols 需要输出的字段，逗号分隔
     * @return array
     * @throws
     */
    function getDicts(string $key, array $where = [], $cols = '*')
    {
        //补充where语句和字段
        if (trim($cols) != '*' && strpos($cols, $key) === false)
        {
            $cols .= ',' . $key;
        }

        //获取数据
        $list = $this->getList($where, $cols);

        //转成字典
        $dict = [];
        foreach ($list as $value)
        {
            $dict[$value[$key]][] = $value;
        }

        //返回
        return $dict;
    }

    /**
     * 通过条件判断是否存在
     * @param array $where 数据条件
     * @return bool
     * @throws
     */
    function exist($where)
    {
        $result = $this->getList($where, '1', [], 1);
        if (count($result) > 0)
        {
            return true;
        }
        return false;
    }

    /**
     * 通过主键值判断是否存在
     * @param mixed $id 主键值
     * @return bool
     * @throws
     */
    function existById($id)
    {
        $where = $this->getIdCondition($id);
        return $this->exist($where);
    }

    /**
     * 插入一条数据
     * @param array $data 需要插入的数据 {xxx:vvv, yy:vv}
     * @param bool $onDuplicateUpdate 重复KEY时是否执行更新
     * @return array|bool
     * @throws
     */
    function insert(array $data, bool $onDuplicateUpdate = false)
    {
        //组装SQL语句
        $cols = [];
        $marks = [];
        $types = [];
        $values = [];
        foreach ($this->cols as $col => $val)
        {
            //主键字段
            if ($this->key[0] == $col && $this->key[1] == MySqlModel::AutoKeyID)
            {
                continue;
            }

            //获取字段类型
            $types[] = $this->getType($col);

            //普通字段
            $cols[] = $col;
            $marks[] = '?';
            if (isset($data[$col]))
            {
                $values[] = $data[$col];
            }
            else
            {
                $values[] = $val[1];
            }
        }

        //合并字符
        $cols = join('`,`', $cols);
        $marks = join(',', $marks);
        $sql = "INSERT INTO `{$this->tbl}` (`$cols`) VALUES ($marks)";

        //更新部分
        if ($onDuplicateUpdate)
        {
            $cols = [];
            foreach ($data as $col => $val)
            {
                //主键字段
                if ($this->key[0] == $col)
                {
                    continue;
                }

                //更新字段
                if (isset($this->cols[$col]))
                {
                    $cols[] = "`$col`=?";
                    $types[] = $this->getType($col);
                    $values[] = $val;
                }
            }
            if (count($cols) > 0)
            {
                $sets = join(',', $cols);
                $sql .= " ON DUPLICATE KEY UPDATE $sets";
            }
        }

        //SQL结束
        $sql .= ';';

        //执行
        $result = $this->execute($sql, $types, $values);

        //分析返回结果
        if ($result['AffectedRows'] > 0)
        {
            if ($this->key[1] == MySqlModel::AutoKeyID)
            {
                return $result['InsertId'];
            }
            else
            {
                return $data[$this->key[0]] ?? true;
            }
        }
        return false;
    }

    /**
     * 批量插入数据
     * @param array $data 批量数据（二维数组）[{xxx:vvv, yy:vv},{}]
     * @return bool
     * @throws
     */
    function inserts(array $data)
    {
        //组装SQL语句
        $cols = [];
        $marks = [];
        $types = [];
        $values = [];
        foreach ($data as $key => $item)
        {
            //组装数值
            $params = [];
            foreach ($this->cols as $col => $val)
            {
                //主键字段
                if ($this->key[0] == $col && $this->key[1] == MySqlModel::AutoKeyID)
                {
                    continue;
                }

                //普通字段
                if ($key == 0)
                {
                    $types[] = $this->getType($col);
                    $cols[] = $col;
                    $marks[] = '?';
                }

                //赋值
                if (isset($item[$col]))
                {
                    $params[] = $item[$col];
                }
                else
                {
                    $params[] = $val[1];
                }
            }
            $values[] = $params;
        }

        //合并字符
        $cols = join('`,`', $cols);
        $marks = join(',', $marks);
        $sql = "INSERT INTO `{$this->tbl}` (`$cols`) VALUES ($marks);";

        //执行
        $result = $this->execute($sql, $types, $values);

        //分析返回结果
        if ($result['AffectedRows'] > 0)
        {
            return $result['Results'];
        }
        return false;
    }

    /**
     * 通过条件更新数据
     * @param array $where 数据条件 ['id' => 123, 'name' => 'xixi', '$or' => [ id => 5, age => 9]]
     * @param array $data 需要更新的数据 {xxx:vvv, yy:vv}
     * @param array $direct 直接更新的数据 {'hits':'hits+1', 'view':'view-1', 'count':'hits+count'}
     * @return bool
     * @throws
     */
    function update(array $where = [], array $data = [], array $direct = [])
    {
        //分析数据条件
        $query = new MySqlQuery($this->cols, $where, []);
        $condition = $query->getCondition();

        //组装SQL
        $cols = [];
        $types = [];
        $values = [];
        foreach ($data as $col => $val)
        {
            if (!isset($this->cols[$col]))
            {
                continue;
            }
            $cols[] = "`$col`=?";
            $types[] = $this->getType($col);
            $values[] = $val;
        }

        //叠加更新
        foreach ($direct as $col => $val)
        {
            if (!isset($this->cols[$col]))
            {
                continue;
            }
            $cols[] = "`$col`=$val";
        }

        //合并字符
        $sets = join(',', $cols);
        $sql = "UPDATE `{$this->tbl}`{$condition['index']} SET $sets {$condition['where']};";

        //合并数值
        $types = array_merge($types, $condition['types']);
        $values = array_merge($values, $condition['values']);

        //执行
        $result = $this->execute($sql, $types, $values);

        //分析返回结果（有个问题：如果更新的内容不变，是不会返回影响行数）
        if ($result['AffectedRows'] > 0)
        {
            return true;
        }
        return $result['Results'] !== false; // PDO方式如果没有改变数据，会返回空数组；
    }

    /**
     * 通过主键值更新数据
     * @param mixed $id 主键值
     * @param array $data 需要更新的数据 {xxx:vvv, yy:vv}
     * @return bool
     * @throws
     */
    function updateById($id, array $data)
    {
        //参数检查
        if (is_array($id))
        {
            throw new DbException('UpdateById ID can\'t be array!');
        }

        $where = $this->getIdCondition($id);
        return $this->update($where, $data);
    }

    /**
     * 通过条件增长数据
     * @param array $where 数据条件 ['id' => 123, 'name' => 'xixi', '$or' => [ id => 5, age => 9]]
     * @param array $data 需要增长的数据 {xxx:1, yy:-2, zz:'yy+1'}
     * @param array $update 需要更新的数据 {xxx:'xx', yyy:'xx'}
     * @return bool
     * @throws
     */
    function increase(array $where = [], array $data = [], array $update = [])
    {
        //分析数据条件
        $query = new MySqlQuery($this->cols, $where, []);
        $condition = $query->getCondition();

        //组装SQL
        $updates = [];
        foreach ($data as $col => $val)
        {
            //忽略掉Model中不存在列、值为空或者0的数据
            if (!isset($this->cols[$col]))
            {
                continue;
            }
            if (is_numeric($val))
            {
                $val = $col . '+' . $val;
            }
            $updates[] = "`$col`=$val";
        }
        foreach ($update as $col => $val)
        {
            if (!isset($this->cols[$col]))
            {
                continue;
            }
            $updates[] = "`$col`='$val'";
        }
        $sets = join(',', $updates);
        $sql = "UPDATE `{$this->tbl}`{$condition['index']} SET $sets {$condition['where']};";

        //执行
        $result = $this->execute($sql, $condition['types'], $condition['values']);

        //分析返回结果（有个问题：如果更新的内容不变，是不会返回影响行数）
        if ($result['AffectedRows'] > 0)
        {
            return true;
        }
        return $result['Results'] !== false; // PDO方式如果没有改变数据，会返回空数组；
    }

    /**
     * 通过主键值增长数据
     * @param mixed $id 主键值
     * @param array $data 需要增长的数据 {xxx:1, yy:-2, zz:yy+1}
     * @param array $update 需要更新的数据 {xxx:'xx', yyy:'xx'}
     * @return bool
     * @throws
     */
    function increaseById($id, array $data = [], array $update = [])
    {
        //参数检查
        if (is_array($id))
        {
            throw new DbException('IncreaseById ID can\'t be array!');
        }

        $where = $this->getIdCondition($id);
        return $this->increase($where, $data, $update);
    }

    /**
     * 通过条件删除数据
     * @param array $where 数据条件 ['id' => 123, 'name' => 'xixi', '$or' => [ id => 5, age => 9]]
     * @return bool
     * @throws
     */
    function delete(array $where = [])
    {
        //分析数据条件
        $query = new MySqlQuery($this->cols, $where, []);
        $condition = $query->getCondition();

        //合并字符
        $sql = "DELETE FROM `{$this->tbl}` {$condition['where']};";

        //执行
        $result = $this->execute($sql, $condition['types'], $condition['values']);

        //分析返回结果（有个问题：如果数据不存在，是不会返回影响行数）
        if ($result['AffectedRows'] > 0)
        {
            return true;
        }
        return false;
    }

    /**
     * 通过主键值删除数据
     * @param mixed $id 主键值
     * @return bool
     * @throws
     */
    function deleteById($id)
    {
        //参数检查
        if (is_array($id))
        {
            throw new DbException('DeleteById ID can\'t be array!');
        }

        $where = $this->getIdCondition($id);
        return $this->delete($where);
    }

    /**
     * 联合表查询
     * @param MySqlModel $model 右表对象
     * @param array $on 联合条件，['左表字段' => '右表字段']
     * @return MySqlJoin
     */
    function join(MySqlModel $model, array $on): MySqlJoin
    {
        return new MySqlJoin($this, $model, $on, []);
    }

    /**
     * 联合表查询
     * @param MySqlModel $model 右表对象
     * @param array $on 联合条件，['左表字段' => '右表字段']
     * @return MySqlJoin
     */
    function leftJoin(MySqlModel $model, array $on): MySqlJoin
    {
        $on['_'] = 'LEFT';
        return new MySqlJoin($this, $model, $on, []);
    }

    /**
     * 获取主键值的数据条件
     * @param mixed $id 主键值
     * @return array
     */
    private function getIdCondition($id)
    {
        $col = $this->key[0];
        return [
            $col => $id
        ];
    }

    /**
     * 获取字段类型
     * @param string $key 字段名称
     * @return string
     */
    private function getType(string $key)
    {
        return $this->cols[$key][0];
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