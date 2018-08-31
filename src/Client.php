<?php
/**
 * Created by PhpStorm.
 * User: chenjiarong
 * Date: 2018/8/29
 * Time: 上午11:13
 */

namespace MegaHttp;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\UriResolver;
use MegaHttp\Exception\ConnectException;
use MegaHttp\Exception\ConnectTimeoutException;
use MegaHttp\Exception\RequestTimeoutException;
use Psr\Http\Message\UriInterface;
use \Swoole\Coroutine\Http\Client as HttpClient;
use function GuzzleHttp\Psr7\uri_for;

class Client
{
    private $config;

    /** @var Pool */
    private $pool;

    public function __construct(array $config = [])
    {
        if (isset($config['base_uri']))
        {
            $config['base_uri'] = uri_for($config['base_uri']);
        }

        if (isset($config['use_pool']) && $config['use_pool'] && !isset($config['base_uri']))
        {
            throw new \InvalidArgumentException('use pool muse be set base_uri option');
        }

        $this->configureDefaults($config);
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function request(string $method, $uri = '', array $options = [])
    {
        $options = $this->prepareDefaults($options);
        $uri     = $this->buildUri($uri, $options);
        $this->applyOptions($options);
        $client = $this->getHttpClient($uri);
        if ($client) {
            $client->set([
                             'timeout'    => $options['timeout'],
                             'keep_alive' => $options['keep_alive']
                         ]);
            $this->handleRequest($client, $method, $uri, $options);
        }

        return false;
    }

    public function get($uri, array $options = [])
    {
        return $this->request('GET', $uri, $options);
    }

    public function post($uri, array $options = [])
    {
        return $this->request('POST', $uri, $options);
    }

    public function put($uri, array $options = [])
    {
        return $this->request('PUT', $uri, $options);
    }

    public function delete($uri, array $options = [])
    {
        return $this->request('DELETE', $uri, $options);
    }

    public function options($uri, array $options = [])
    {
        return $this->request('OPTIONS', $uri, $options);
    }

    public function head($uri, array $options = [])
    {
        return $this->request('head', $uri, $options);
    }

    /**
     * @param UriInterface $uri
     * @return HttpClient
     */
    protected function getHttpClient(UriInterface $uri)
    {
        if ($this->config['use_pool'])
        {
            if(isset($this->pool))
            {
                return $this->pool->get();
            }

            $poolConfig = ['factory' => $this->createHttpClient($uri)] + ($this->config['pool'] ?? []);
            $this->pool = new Pool($poolConfig);
            return $this->pool->get();
        }
        else
        {
            return $this->createHttpClient($uri)();
        }
    }

    /**
     * @param UriInterface $uri
     * @return callable
     */
    protected function createHttpClient(UriInterface $uri)
    {
        return function() use ($uri) {
            $port = $uri->getPort();
            if (!is_int($port))
            {
                switch ($uri->getScheme())
                {
                    case 'http':
                        $port = 80;
                        break;
                    case 'https':
                        $port = 443;
                        break;
                    default:
                        $port = 80;
                }
            }

            $client = new HttpClient($uri->getHost(), $port, ($this->config['ssl'] ?? false) ?: $port === 443);
            return $client;
        };
    }

    protected function handleRequest(HttpClient $client, string $method, UriInterface $uri, $options)
    {
        $method = strtoupper($method);
        $path   = $uri->getPath();

        if (isset($options['headers']))
        {
            $client->setHeaders($options['headers']);
        }

        switch ($method)
        {
            case 'POST':
                $client->post($path, $options['body']);
                break;
            case 'GET':
                $client->get($path);
                break;
            default:
                $client->setMethod($method);
                $client->setData($client['body']);
                $client->execute($path);
        }

        $body = $client->body;
        $response = new Response($client->statusCode, [], $body);


        if ($client->statusCode < 0)
        {
            if (null !== $this->pool)
            {
                $this->pool->closeConn($client);
            }

            switch ($client->statusCode)
            {
                case -1:
                    $msg = socket_strerror($client->errCode);
                    throw new ConnectTimeoutException($msg, -1);
                case -2:
                    throw new RequestTimeoutException('{$method}:' . (string)$uri . ' request timeout', -2);
                case -3:
                    throw new ConnectException('server force close connection', -3);
                default:
                    throw new ConnectException('error', $client->statusCode);
            }
        }

        if (null !== $this->pool)
        {
            $this->pool->put($client);
        }

        return $response;
    }

    public function close()
    {
        if (null !== $this->pool)
        {
            $this->pool->close();
            unset($this->pool);
        }
    }

    private function configureDefaults(array $config)
    {
        $defaults = [
            'timeout'    => 10,
            'keep_alive' => true,
            'use_pool'   => false,
        ];

        $this->config = $config + $defaults;
    }

    private function prepareDefaults(array $options)
    {
        $defaults = $this->config;

        if (array_key_exists('headers', $options))
        {
            if ($options['headers'] === null)
            {
                unset($defaults['headers']);
                unset($options['headers']);
            }
            else if (!is_array($options['headers']))
            {
                throw new \InvalidArgumentException('header must be array');
            }
        }

        $rs = $options + $defaults;
        foreach ($rs as $k => $v)
        {
            if ($v === null)
            {
                unset($rs[$k]);
            }
        }

        return $rs;
    }

    private function applyOptions(array &$options)
    {
        if (is_array($options['body'] ?? null))
        {
            throw new \InvalidArgumentException('body option must not be array');
        }

        if (isset($options['form_params']))
        {
            $options['body'] = $options['form_params'];
        }

        if (isset($options['json']))
        {
            $json = \json_encode($options['json']);
            if (JSON_ERROR_NONE !== json_last_error())
            {
                throw new \InvalidArgumentException('json_encode error: ' . json_last_error_msg());
            }
            $options['body'] = $json;
        }

        if (!isset($options['body']))
        {
            $options['body'] = null;
        }
    }

    private function buildUri($uri, array $config)
    {
        $uri = uri_for($uri === null ? '' : $uri);
        if (isset($config['base_uri']))
        {
            $uri = UriResolver::resolve(uri_for($config['base_uri']), $uri);
        }
        return $uri->getScheme() === '' && $uri->getHost() !== '' ? $uri->withScheme('http') : $uri;
    }
}