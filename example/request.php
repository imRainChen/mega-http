<?php
/**
 * Created by PhpStorm.
 * User: chenjiarong
 * Date: 2018/8/29
 * Time: 下午4:09
 */

require __DIR__ . '/../vendor/autoload.php';

//go(function() {
//    $client = new \MegaHttp\Client([
//        'base_uri' => '127.0.0.1:8901/api/',
//        'use_pool' => true
//    ]);
//
//    $count = 1;
//    for ($i = 0; $i < $count; $i++)
//    {
//        $rsp = $client->request('post', '', [
//            'form_params' => [
//                'a' => 'b'
//            ]
//        ]);
//        var_dump((string)$rsp->getBody());
//    }
//
//    $client->close();
//});

go(function() {
    $client = new \MegaHttp\Client([
                                       'base_uri' => '127.0.0.1:8901/api/',
                                       'use_pool' => true,
                                       'pool' => [
                                           'initial_size' => 0,
                                           'max_size' => 2,
                                           'checkout_timeout' => 1,
                                           'acquire_retry_attempts' => 2,
                                       ]
                                   ]);



        $cap = 3;
        $chan = new chan($cap);
        go(function() use(&$client, $chan) {
            $rsp = $client->request("get", 'oauth/xd');
            $chan->push($rsp);
        });
        go(function() use(&$client, $chan) {
            $rsp = $client->request("get", 'oauth/xd');
            $chan->push($rsp);
        });
        go(function() use(&$client, $chan) {
            try
            {
            $rsp = $client->request("get", 'oauth/xd');
            $chan->push($rsp);
            }
            catch (\RuntimeException $e)
            {
                var_dump('111');
            }
        });

        $result = [];
        for ($i = 0; $i < $cap; $i++)
        {
            $result[] = $chan->pop(5);
        }
        foreach ($result as $rs)
        {
            var_dump((string)$rs->getBody());
        }
        $client->close();
});

//$server = new swoole_server("127.0.0.1", 9503);
//$server->on('connect', function ($server, $fd){
//    echo "connection open: {$fd}\n";
//});
//$server->on('receive', function ($server, $fd, $reactor_id, $data) {
//    var_dump($data);
//    $chan = new chan(2);
//    go(function() use ($chan) {
//        $client = new \MegaHttp\Client();
//        $rsp = $client->request('post', 'http://127.0.0.1:8901/api/oauth/xd', [
//            'form_params' => [
//                'a' => 'b'
//            ]
//        ]);
//
//        $chan->push($rsp);
//    });
//
//
//    go(function() use ($chan) {
//        $client = new \MegaHttp\Client();
//        $rsp = $client->request('post', 'http://127.0.0.1:8901/api/oauth/xd', [
//            'form_params' => [
//                'a' => 'b'
//            ]
//        ]);
//        $chan->push($rsp);
//    });
//
//    $result = [];
//    for ($i = 0; $i < 2; $i++)
//    {
//        $result[] = $chan->pop();
//    }
//
//    foreach ($result as $rs)
//    {
//        var_dump((string)$rs->getBody());
//    }
//});
//$server->on('close', function ($server, $fd) {
//    echo "connection close: {$fd}\n";
//});
//$server->start();

//go(function() {
//    $chan = new chan(2);
//    go(function() use ($chan) {
//        $client = new \MegaHttp\Client();
//        $rsp = $client->request('post', 'http://127.0.0.1:8901/api/oauth/xd', [
//            'form_params' => [
//                'a' => 'b'
//            ]
//        ]);
//
//        $chan->push($rsp);
//    });
//
//
//    go(function() use ($chan) {
//        $client = new \MegaHttp\Client();
//        $rsp = $client->request('post', 'http://127.0.0.1:8901/api/oauth/xd', [
//            'form_params' => [
//                'a' => 'b'
//            ]
//        ]);
//        $chan->push($rsp);
//    });
//
//    $result = [];
//    for ($i = 0; $i < 2; $i++)
//    {
//        $result[] = $chan->pop();
//    }
//
//    foreach ($result as $rs)
//    {
//        var_dump((string)$rs->getBody());
//    }
//});