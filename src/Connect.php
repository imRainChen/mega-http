<?php
/**
 * Created by PhpStorm.
 * User: chenjiarong
 * Date: 2018/8/30
 * Time: 下午2:49
 */

namespace MegaHttp;

class Connect
{
    /**
     * @var \Swoole\Coroutine\Http\Client
     */
    private $conn;

    /**
     * @var int
     */
    private $time;

    public function __construct(\Swoole\Coroutine\Http\Client $conn, int $time = null)
    {
        $this->conn = $conn;
        $this->time = $time ?: time();
    }

    public function getTime()
    {
        return $this->time;
    }

    public function getConn()
    {
        return $this->conn;
    }
}