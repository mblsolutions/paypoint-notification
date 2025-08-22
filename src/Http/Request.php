<?php

namespace MBLSolutions\Notification\Http;

use GuzzleHttp\Psr7\Request as Psr7Request;

class Request extends Psr7Request
{
    protected $content;

    /**
     * @param string                               $method  HTTP method
     * @param string|UriInterface                  $uri     URI
     * @param (string|string[])[]                  $headers Request headers
     * @param string|resource|StreamInterface|null $body    Request body
     * @param string                               $version Protocol version
     */
    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        $body = null,
        string $version = '1.1'
    ) {
        parent::__construct($method,$uri,$headers,$body,$version);
        //get the request content before client consuming it
        $this->content = parent::getBody()->getContents();
    }

    public function getContents(): string
    {
        return $this->content;
    }
}