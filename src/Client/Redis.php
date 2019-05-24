<?php
namespace Swork\Client;

use Swork\Pool\PoolCollector;
use Swork\Pool\PoolInterface;
use Swork\Initialize;

class Redis
{
    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * 初始化获取连接池
     * Redis constructor.
     */
    public function __construct()
    {
        $this->pool = PoolCollector::getCollector(PoolCollector::Redis, 'default');
    }

    public function get($key)
    {
        return $this->call('get', $key);
    }

    public function set($key, $data, $timeout = 0)
    {
        $rel = $this->call('set', $key, [$data]);
        if($rel == true && $timeout > 0)
        {
            $this->call('expire', $key, [$timeout]);
        }
        return $rel;
    }
    
    public function incr($key)
    {
        return $this->call('incr', $key);
    }

    public function incrBy($key, $value)
    {
        return $this->call('incrBy', $key, [$value]);
    }

    public function decr($key)
    {
        return $this->call('decr', $key);
    }

    public function decrBy($key, $value)
    {
        return $this->call('decrBy', $key, [$value]);
    }

    public function getRange($key, $start, $end)
    {
        return $this->call('getRange', $key, [$start, $end]);
    }

    public function lPush($key, $value)
    {
        return $this->call('lPush', $key, [$value]);
    }

    public function rPush($key, $value)
    {
        return $this->call('rPush', $key, [$value]);
    }

    public function lPop($key)
    {
        return $this->call('lPop', $key);
    }

    public function rPop($key)
    {
        return $this->call('rPop', $key);
    }

    public function lLen($key)
    {
        return $this->call('lLen', $key);
    }

    public function expire($key, $second)
    {
        return $this->call('expire', $key, [$second]);
    }

    public function expireAt($key, $time)
    {
        return $this->call('expireAt', $key, [$time]);
    }

    public function del($key)
    {
        return $this->call('del', $key);
    }

    public function exists($key)
    {
        return $this->call('exists', $key);
    }

    public function sAdd($key, $value)
    {
        return $this->call('sAdd', $key, [$value]);
    }

    public function sRem($key, $item)
    {
        return $this->call('sRem', $key, [$item]);
    }

    public function sRandMember($key, $count)
    {
        return $this->call('sRandMember', $key, [$count]);
    }

    public function sMembers($key)
    {
        return $this->call('sMembers', $key);
    }

    public function sIsMember($key, $value)
    {
        return $this->call('sIsMember', $key, [$value]);
    }

    public function sCard($key)
    {
        return $this->call('sCard', $key);
    }

    public function sPop($key)
    {
        return $this->call('sPop', $key);
    }

    public function setnx($key, $value, $timeout = 0)
    {
        $rel = $this->call('setnx', $key, [$value]);
        if($rel == true && $timeout > 0)
        {
            $this->call('expire', $key, [$timeout]);
        }
        return $rel;
    }

    public function hSet($key, $field, $value)
    {
        return $this->call('hSet', $key, [$field, $value]);
    }

    public function hGet($key, $field)
    {
        return $this->call('hGet', $key, [$field]);
    }

    public function hLen($key)
    {
        return $this->call('hLen', $key);
    }

    public function hExists($key, $field)
    {
        return $this->call('hExists', $key, [$field]);
    }

    public function hDel($key, $field)
    {
        return $this->call('hDel', $key, [$field]);
    }

    public function hMGet($key, $fields)
    {
        return $this->call('hMGet', $key, [$fields]);
    }

    public function hGetAll($key)
    {
        return $this->call('hGetAll', $key);
    }

    public function hSetNx($key, $field, $value)
    {
        return $this->call('hSetNx', $key, [$field, $value]);
    }

    public function has($key)
    {
        return $this->call('exists', $key);
    }

    private function call(string $name, string $key, array $arguments = [])
    {
        $result = false;
        $conn = $this->pool->getConnection();

        try
        {
            $result = $conn->$name($key, $arguments);
        }
        catch (\RedisException $exception)
        {

        }
        catch (\Exception $exception)
        {

        }
        finally
        {
            $this->pool->releaseConnection($conn);
        }

        return $result;
    }
}
