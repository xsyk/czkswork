<?php
namespace Swork\Pool\MySql;

use Swork\Pool\AbstractPool;
use Swork\Pool\ConnectionInterface;

/**
 * MySQL连接池
 */
class MySqlPool extends AbstractPool
{
    /**
     * 创建连接
     * @param int $type 连接池类型
     * @return ConnectionInterface
     * @throws
     */
    public function createConnection(int $type): ConnectionInterface
    {
        //创建连接对象
        $conn = new MySqlConnection($this->config);
        $conn->setType($type);
        $conn->create();

        //返回
        return $conn;
    }
}
