<?php
namespace Swork\Pool\MySql;

use Swoole\Coroutine\MySQL;
use Swork\Bean\Holder\InstanceHolder;
use Swork\Exception\ConnectionException;
use Swork\Exception\DbException;
use Swork\Pool\AbstractConnection;
use Swork\Pool\DbConnectionInterface;
use Swork\Pool\Types;
use Swork\Service;

/**
 * Interface ConnectInterface
 * @package Swoft\Pool
 */
class MySqlConnection extends AbstractConnection implements DbConnectionInterface
{
    /**
     * 配置信息
     * @var array
     */
    private $options;

    /**
     * 当前连接对象
     * @var MySQL|\PDO
     */
    private $connection;

    /**
     * 当前运行的SQL和数据
     * @var string
     */
    private $sql;
    private $datas;

    /**
     * SQL脚本过滤器
     * @var string
     */
    private $filter;

    /**
     * SQL脚本执行记录器
     * @var string
     */
    private $recorder;

    /**
     * 是否手动给sql插值
     * @var bool
     */
    private $sqlManualInter;

    /**
     * 最后插入ID
     * @var int
     */
    private $insert_id;

    /**
     * 影响行数
     * @var int
     */
    private $affected_rows;

    /**
     * 开始运行时间
     * @var float
     */
    private $startTime;

    /**
     * 无法连接至服务器
     */
    const CONNECT_FAILED = 'Cannot connect to MySQL Server.';

    /**
     * 销毁的时候执行一次接收命令
     */
    public function __destruct()
    {
        if ($this->connection == null)
        {
            return;
        }

        //关闭连接
        if ($this->getType() == Types::Coroutine)
        {
            $this->connection->close();
        }
        else
        {
            $this->connection = null;
        }
    }

    /**
     * Create connectioin
     * @return void
     * @throws
     */
    public function create()
    {
        //获取配置
        $this->options = array_merge([
            'host' => '127.0.0.1',
            'port' => 3306,
            'charset' => 'utf8mb4',
            'timeout' => 0,
            'filter' => null,
            'recorder' => null,
            'sqlManualInter' => false
        ], $this->config->getUri());

        //载入配置
        $this->filter = $this->options['filter'];
        $this->recorder = $this->options['recorder'];
        $this->sqlManualInter = $this->options['sqlManualInter'];

        //连接db
        $this->reconnect();
    }

    /**
     * 连接Mysql
     * @throws
     */
    private function connectMysql()
    {
        try
        {
            //连接服务器
            $this->connection = new MySQL();
            $this->connection->connect($this->options);

            //连接失败则抛出异常
            if ($this->connection == false || $this->connection->connect_errno > 0)
            {
                throw new DbException(self::CONNECT_FAILED);
            }
//            if ($this->connection->connect_errno > 0)
//            {
//                throw new DbException(self::CONNECT_FAILED);
//            }
        }
        catch (\Throwable $exception)
        {
            var_dump($exception->getMessage());
            var_dump($exception->getCode());
            throw $exception;
        }
    }

    /**
     * 连接PDO
     * @throws
     */
    private function connectPDO()
    {
        //获取配置（PDO的ATTR_TIMEOUT不能小于0）
        $opts = $this->options;
        $opts['timeout'] = $opts['timeout'] < 0 ? 0 : $opts['timeout'];

        //连接配置
        $dsn = "mysql:host={$opts['host']};port={$opts['port']};dbname={$opts['database']};charset={$opts['charset']}";
        $pdoOpts = [
            \PDO::ATTR_PERSISTENT => true,
            \PDO::ATTR_TIMEOUT => $opts['timeout'],
        ];

        //连接服务器
        try
        {
            $this->connection = new \PDO($dsn, $opts['user'], $opts['password'], $pdoOpts);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        }
        catch (\PDOException $e)
        {
            throw new DbException(self::CONNECT_FAILED);
        }
    }

    /**
     * 重新连接
     * @throws
     */
    public function reconnect()
    {
        if ($this->getType() == Types::Coroutine)
        {
            $this->connectMysql();
        }
        else
        {
            $this->connectPDO();
        }
    }

    /**
     * 检查是否连接中
     * @return bool
     */
    public function check(): bool
    {
        if ($this->connection == null)
        {
            return false;
        }

        //分别判断连接是否正常
        if ($this->getType() == Types::Coroutine)
        {
            return $this->connection->connected;
        }
        else
        {
            try
            {
                $this->connection->getAttribute(\PDO::ATTR_SERVER_INFO);
            }
            catch (\Throwable $e)
            {
                if ($e->getCode() == 'HY000')
                {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 执行SQL语句，并返回结果
     * @param string $sql 待运行的SQL语句， 如 SELECT * FROM T WHERE id=?
     * @param array $types SQL预处理后需要的字段类型
     * @param array $datas SQL预处理后需要的字段数值
     * @return array|bool
     * @throws
     */
    public function query(string $sql, array $types = [], array $datas = [])
    {
        $result = false;

        //过滤SQL（引用的方式）
        if ($this->filter != null)
        {
            $method = 'process';
            $inc = InstanceHolder::getClass($this->filter);
            $inc->$method($sql, $datas);
        }

        //记录当前运行SQL和数据
        $this->sql = $sql;
        $this->datas = $datas;

        //重试连接Mysql（最多三次）
        for ($tryNum = 0; $tryNum < 3; $tryNum += 1)
        {
            //打印重连信息
            if ($tryNum > 0)
            {
                Service::$logger->alert('Mysql|PDO reconnect number: ' . $tryNum);
            }

            //执行SQL
            try
            {
                //如果没有参数
                if (count($types) == 0)
                {
                    $result = $this->querySimple($sql);
                }
                else
                {
                    //如果是批量操作
                    if (is_array($datas[0]))
                    {
                        $result = $this->queryBatch($sql, $types, $datas);
                    }
                    else
                    {
                        $result = $this->queryParams($sql, $types, $datas);
                    }
                }

                //成功，退出循环
                break;
            }
            catch (ConnectionException $exception)
            {
                //达到三次，不重试了
                if ($tryNum == 2)
                {
                    throw new DbException("Querying lose MySQLConnect " . $exception->getMessage());
                }
                $this->reconnect();
            }
            catch (\Throwable $exception)
            {
                throw new DbException($exception->getMessage());
            }
        }

        //返回
        return $result;
    }

    /**
     * 执行简单的SQL语句（不需要参数）
     * @param string $sql 待运行的SQL语句
     * @return bool|mixed
     * @throws
     */
    private function querySimple(string $sql)
    {
        //执行结果（区分协程和同步）
        $result = null;
        if ($this->getType() == Types::Coroutine)
        {
            //协程方式多一个超时时间参数
            $stmt = $this->connection->query($sql, $this->options['timeout']);
            if ($stmt === false)
            {
                $this->dispatchException($sql);
            }
            $result = $stmt;
            $this->insert_id = $this->connection->insert_id;
            $this->affected_rows = $this->connection->affected_rows;
        }
        else
        {
            //PDO方式不设置第二个参数
            $stmt = $this->connection->query($sql);
            if ($stmt === false)
            {
                $this->dispatchException($sql);
            }
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->insert_id = $this->connection->lastInsertId();
            $this->affected_rows = $stmt->rowCount();
        }

        //获取结果
        return $result;
    }

    /**
     * 执行带有N层参数的SQL语句（批量执行）
     * @param string $sql 待运行的SQL语句
     * @param array $types SQL预处理后需要的字段类型
     * @param array $datas SQL预处理后需要的字段数值
     * @return bool|mixed
     * @throws
     */
    private function queryBatch(string $sql, array $types, array $datas)
    {
        //影响行数统计
        $affected_rows = 0;

        //区分手动插值还是自动插值
        if ($this->sqlManualInter)
        {
            //逐条处理
            foreach ($datas as $data)
            {
                //插值
                $_sql = $sql;
                $this->interSqlValue($_sql, $types, $data);

                //执行
                $this->querySimple($_sql);

                //累加
                $affected_rows += $this->affected_rows;
            }
        }
        else
        {
            //预处理SQL
            $stmt = $this->prepStatement($sql, $types, $datas);

            //逐条处理
            foreach ($datas as $data)
            {
                //执行
                $this->execStatement($stmt, $sql, $types, $data);

                //累加结果
                if ($this->getType() == Types::Coroutine)
                {
                    $affected_rows += $this->connection->affected_rows;
                }
                else
                {
                    $affected_rows += $stmt->rowCount();
                }
            }

            //释放资源
            unset($stmt);
        }

        //执行结果
        $this->insert_id = 0;
        $this->affected_rows = $affected_rows;

        //返回（只要不发生异常，都认为是正常的）
        return true;
    }

    /**
     * 执行带有一层参数的SQL语句
     * @param string $sql 待运行的SQL语句
     * @param array $types SQL预处理后需要的字段类型
     * @param array $datas SQL预处理后需要的字段数值
     * @return bool|mixed
     * @throws
     */
    private function queryParams(string $sql, array $types, array $datas)
    {
        //区分手动插值还是自动插值
        $result = null;
        if ($this->sqlManualInter)
        {
            //插值
            $this->interSqlValue($sql, $types, $datas);

            //执行
            $result = $this->querySimple($sql);
        }
        else
        {
            //预处理SQL
            $stmt = $this->prepStatement($sql, $types, $datas);

            //执行命令
            $exceResult = $this->execStatement($stmt, $sql, $types, $datas);

            //获取结果
            $result = null;
            if ($this->getType() == Types::Coroutine)
            {
                $result = $exceResult;

                //执行结果
                $this->insert_id = $this->connection->insert_id;
                $this->affected_rows = $this->connection->affected_rows;
            }
            else
            {
                $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                //执行结果
                $this->insert_id = $this->connection->lastInsertId();
                $this->affected_rows = $stmt->rowCount();
            }
        }

        //释放资源
        unset($stmt);

        //返回
        return $result;
    }

    /**
     * @param string $sql
     * @param array $types
     * @param array $datas
     * @return MySQL\Statement|\PDOStatement
     * @throws
     */
    private function prepStatement(string $sql, array $types, array $datas)
    {
        try
        {
            $stmt = $this->connection->prepare($sql);
            if ($stmt == false)
            {
                throw new \Exception();
            }
            return $stmt;
        }
        catch (\Throwable $e)
        {
            //分发异常
            $this->dispatchException($sql, $types, $datas);

            //返回
            return null;
        }
    }

    /**
     * 执行
     * @param $statement MySQL\Statement|\PDOStatement
     * @param $sql
     * @param $types
     * @param $datas
     * @return mixed
     * @throws DbException
     */
    private function execStatement($statement, string $sql, array $types = [], array $datas = [])
    {
        $result = null;
        $typesStr = join('', $types);
        $datasStr = json_encode($datas, JSON_UNESCAPED_UNICODE);
        if ($this->getType() == Types::Coroutine)
        {
            $result = $statement->execute($datas, $this->options['timeout']);
            if ($result === false)
            {
                throw new DbException(sprintf('MySQL execute error. [%s], [%s], [%s], [%s]',
                    $sql, $typesStr, $datasStr, $statement->error));
            }
        }
        else
        {
            //捕捉PDO异常
            try
            {
                $statement->execute($datas);
            }
            catch (\PDOException $e)
            {
                throw new DbException(sprintf('PDO execute error. [%s], [%s], [%s], [%s]',
                    $sql, $typesStr, $datasStr, $e->getMessage()));
            }
        }

        //返回
        return $result;
    }

    /**
     * 将值插入到预处理语句中
     * @param string $sql 预处理语句
     * @param array $types 类型
     * @param array $datas 值
     */
    private function interSqlValue(string &$sql, array &$types, array &$datas)
    {
        $pArr = explode('?', $sql);
        $newSql = $pArr[0] ?? '';
        $length = count($pArr);
        for ($idx = 0; $idx < $length - 1; $idx += 1)
        {
            $value = $datas[$idx];
            if ($types[$idx] == 's')
            {
                $value = "'$value'";
            }
            $newSql .= $value . $pArr[$idx + 1];
        }

        //重新赋值
        $sql = $newSql;
        $datas = [];
    }

    /**
     * @return mixed
     */
    public function getInsertId()
    {
        return $this->insert_id;
    }

    /**
     * @return int
     */
    public function getAffectedRows(): int
    {
        return $this->affected_rows;
    }

    /**
     * Begin transaction
     * @return array|bool
     */
    public function beginTransaction()
    {
        if ($this->getType() == Types::Coroutine)
        {
            return $this->connection->query('begin;');
        }
        else
        {
            return $this->connection->beginTransaction();
        }
    }

    /**
     * Rollback transaction
     * @return array|bool
     */
    public function rollback()
    {
        if ($this->getType() == Types::Coroutine)
        {
            return $this->connection->query('rollback;');
        }
        else
        {
            return $this->connection->rollback();
        }
    }

    /**
     * Commit transaction
     * @return array|bool
     */
    public function commit()
    {
        if ($this->getType() == Types::Coroutine)
        {
            return $this->connection->query('commit;');
        }
        else
        {
            return $this->connection->commit();
        }
    }

    public function useDB($dbName)
    {
        // 区分协程mysql和pdo
        if ($this->getType() == Types::Coroutine) {
            $this->query('use ' . $dbName);
        } else {
            $this->connection->exec('use ' . $dbName);
        }
    }

    public function startRecord()
    {
        $this->startTime = microtime(true);
    }

    public function stopRecord($throwable = null)
    {
        if ($this->recorder != null)
        {
            $method = 'process';
            $inc = InstanceHolder::getClass($this->recorder);
            $inc->$method($this->startTime, microtime(true), [$this->sql, $this->datas], $throwable);
        }
    }

    /**
     * 分发异常（区分协程同步，区分连接异常和执行异常）
     * @param string $sql
     * @param array $types
     * @param array $datas
     * @throws ConnectionException
     * @throws DbException
     */
    private function dispatchException(string $sql, array $types = [], array $datas = [])
    {
        //区分协程和同步
        $typesStr = join('', $types);
        $datasStr = json_encode($datas, JSON_UNESCAPED_UNICODE);
        if ($this->getType() == Types::Coroutine)
        {
            //协程方式处理
            $connect_errno = $this->connection->connect_errno;
            $errno = $this->connection->errno;
            if ($connect_errno > 0)
            {
                throw new ConnectionException($this->connection->connect_error);
            }
            throw new DbException(sprintf('MySQL query error. [%s], [%s], [%s], [%s]', $sql, $typesStr, $datasStr, $this->connection->error), $errno);
        }
        else
        {
            $errorInfo = $this->connection->errorInfo();
            if ($errorInfo[0] == 'HY000')
            {
                throw new ConnectionException($errorInfo[2]);
            }
            throw new DbException(sprintf('PDO query error. [%s], [%s], [%s], [%s]', $sql, $typesStr, $datasStr, $errorInfo[2]), $errorInfo[1]);
        }
    }
}
