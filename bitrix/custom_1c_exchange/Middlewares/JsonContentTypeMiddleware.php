<?php

namespace Exchange\Middlewares;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class JsonContentTypeMiddleware
{
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler)
    {
        $response = $handler->handle($request);
        return $response->withHeader('Content-type' ,'application/json');
    }
}