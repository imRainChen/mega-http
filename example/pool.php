<?php
/**
 * Created by PhpStorm.
 * User: chenjiarong
 * Date: 2018/8/31
 * Time: 下午3:36
 */
require __DIR__ . '/../vendor/autoload.php';

$factoryFunc = function() {
    $client = new \Swoole\Coroutine\Http\Client('127.0.0.1', 8901);
    return $client;
};

go(function() use ($factoryFunc) {
    $pool = new \MegaHttp\Pool([
        'factory' => $factoryFunc,
        'checkout_timeout' => 1,
        'acquire_retry_attempts' => 10,
        'idle_timeout' => 1,
        'initial_size' => 0,
        'max_size' => 10,

                               ]);
    $count = 100;
    for ($i = 0; $i < $count; $i++)
    {
        $client = $pool->get();
        if (!$client)
        {
            var_dump('fail');
        }
        $pool->put($client);

    }
});