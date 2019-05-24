<?php
namespace Swork\Pool;

/**
 * 连接池类型
 * Class Types
 * @package Swork\Pool
 */
class Types
{
    /**
     * 普通连接池
     */
    const Normal = 1;

    /**
     * 协程连接池
     */
    const Coroutine = 2;
}