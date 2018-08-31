<?php
/**
 * Created by PhpStorm.
 * User: chenjiarong
 * Date: 2018/8/30
 * Time: 下午2:25
 */

namespace MegaHttp;

use MegaHttp\Exception\PoolException;
use Swoole\Coroutine\Channel;
use \Swoole\Coroutine\Http\Client;

class Pool
{
    /**
     * 连接初始数量
     *
     * @var int|mixed
     */
    protected $initialSize = 0;

    /**
     * 连接池最大数量
     * 超过该数量将不再构造新的连接
     *
     * @var int|mixed
     */
    protected $maxSize = 30;

    /**
     * 连接空闲时间
     * 超过空闲时间，将被关闭
     *
     * @var int|mixed
     */
    protected $idleTimeout = 0;

    /**
     * 获取连接超时时间
     *
     * @var float
     */
    protected $checkoutTimeout = 0;

    /**
     * 连接池在获得新连接失败时重试的次数
     *
     * @var int
     */
    protected $acquireRetryAttempts = 0;

    /**
     * @var callable
     */
    protected $factory;

    /**
     * @var \Swoole\Coroutine\Channel
     */
    private $chan;

    private $connCount = 0;

    private $acquireRetryCount = 0;

    private $isClose = false;

    public function __construct($params = [])
    {
        if (isset($params['initial_size']))
        {
            $this->initialSize = $params['initial_size'];
        }

        if (isset($params['max_size']))
        {
            $this->maxSize = $params['max_size'];
        }

        if (isset($params['idle_timeout']))
        {
            $this->idleTimeout = $params['idle_timeout'];
        }

        if (isset($params['checkout_timeout']))
        {
            $this->checkoutTimeout = (float)$params['checkout_timeout'];
        }

        if (isset($params['acquire_retry_attempts']))
        {
            $this->acquireRetryAttempts = $params['acquire_retry_attempts'];
        }

        if (!isset($params['factory']) && !is_callable($params['factory']))
        {
            throw new \InvalidArgumentException('factory must be set');
        }

        $this->factory = $params['factory'];

        if ($this->initialSize < 0 || $this->maxSize <= 0 || $this->initialSize > $this->maxSize)
        {
            throw new \InvalidArgumentException('invalid capacity settings');
        }

        $this->chan = new Channel($this->maxSize);

        for ($i = 0; $i < $this->initialSize; $i++)
        {
            $this->chan->push($this->tryGetConn());
        }
    }

    /**
     * 获取一个连接
     *
     * @return Client|bool
     */
    public function get()
    {
        do
        {
            if ($this->isClose)
            {
                throw new PoolException('pool is closed');
            }

            if ($this->chan->isEmpty())
            {
                $conn = $this->tryGetConn();
                if (false !== $conn)
                {
                    return $conn->getConn();
                }
            }

            /** @var Connect $conn */
            $conn = $this->chan->pop($this->checkoutTimeout);
            if ($conn === false)
            {
                if ($this->acquireRetryAttempts <= 0 || $this->acquireRetryAttempts > $this->acquireRetryCount)
                {
                    $this->acquireRetryCount++;
                    continue;
                }
                else
                {
                    throw new PoolException('retry get connect has the maximum limit');
                }
            }


            $this->acquireRetryCount = 0;

            // 连接空闲时间检测
            if ($this->idleTimeout > 0 && time() - $conn->getTime() >= $this->idleTimeout)
            {
                $this->closeConn($conn->getConn());
                continue;
            }

            $this->connCount--;
            return $conn->getConn();
        }
        while (true);
    }

    public function put(Client $client)
    {
        if ($this->isClose)
        {
            throw new PoolException('pool is closed');
        }

        if ($client === null)
        {
            throw new \InvalidArgumentException('client is null');
        }

        if ($this->chan->isFull())
        {
            return $this->closeConn($client);
        }

        $this->connCount++;
        return $this->chan->push(new Connect($client));
    }

    public function getLength()
    {
        return $this->chan->length();
    }

    public function closeConn(Client $conn)
    {
        $this->connCount--;
        return $conn->close();
    }

    public function release()
    {
        while($conn = $this->chan->pop())
        {
            $this->closeConn($conn->getConn());
            if ($this->chan->isEmpty())
            {
                return;
            }
        }
    }

    public function close()
    {
        if ($this->isClose)
        {
            return;
        }

        $this->isClose = true;
        $this->release();
        $this->chan->close();
    }

    /**
     * 尝试获取一个连接
     *
     * @return bool|Connect
     */
    protected function tryGetConn()
    {
        if ($this->connCount < $this->maxSize)
        {
            $conn = ($this->factory)();
            if (!$conn instanceof Client)
            {
                throw new \InvalidArgumentException('factory is not able to fill the pool');
            }

            if ($conn)
            {
                $this->connCount++;
            }

            return new Connect($conn);
        }
        else
        {
            return false;
        }
    }

}