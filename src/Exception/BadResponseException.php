<?php
/**
 * Created by PhpStorm.
 * User: chenjiarong
 * Date: 2018/8/29
 * Time: 下午3:20
 */

namespace MegaHttp\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class BadResponseException extends \RuntimeException
{
    private $request;

    private $response;

    public function __construct(RequestInterface $request, ResponseInterface $response, Throwable $previous = null)
    {
        $code = $response ? $response->getStatusCode() : 0;
        $message = sprintf(
            '%s:%s resulted in a `%s %s` response',
            $request->getMethod(),
            $request->getUri(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );
        parent::__construct($message, $code, $previous);
        $this->request = $request;
        $this->response = $response;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getRequest()
    {
        return $this->request;
    }

}