<?php

namespace Webgraphe\Phollow\Components;

use Webgraphe\Phollow\Tracer;

class HttpRequestHandler
{
    /** @var Tracer */
    private $tracer;
    /** @var \FastRoute\Dispatcher */
    private $dispatcher;
    /** @var string */
    private $origin;

    public function __construct(Tracer $tracer, $origin = '')
    {
        $this->tracer = $tracer;
        $this->origin = $origin;
        $this->dispatcher = \FastRoute\simpleDispatcher(
            function (\FastRoute\RouteCollector $routes) {
                $routes->addRoute(
                    'GET',
                    '/',
                    function () {
                        // TODO Return single page application that consumes the other endpoints
                        return new \React\Http\Response(200, ['Content-Type' => 'text/plain'], 'Welcome');
                    }
                );
                // TODO Add data routes
            }
        );
    }

    /**
     * Makes the class callable by \React\Http\Server.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return \React\Http\Response
     */
    public function __invoke(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        if ($this->origin) {
            if ($request->getUri()->getHost() !== $this->origin) {
                return $this->forbiddenResponse($method, $path);
            }
        }

        $routeInfo = $this->dispatcher->dispatch($method, $path);

        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                return $this->notFoundResponse($method, $path);
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                return $this->methodNotAllowedResponse($method, $path);
            case \FastRoute\Dispatcher::FOUND:
            default:
                {
                    /** @var \React\Http\Response $response */
                    $response = $routeInfo[1]($request, ... array_values($routeInfo[2]));
                    $this->tracer->info("{$response->getStatusCode()} $method $path");

                    return $response;
                }
        }
    }

    /**
     * @param string $method
     * @param string $path
     * @return \React\Http\Response
     */
    private function forbiddenResponse($method, $path)
    {
        static $response;
        if (!$response) {
            $response = new \React\Http\Response(
                403,
                [
                    'Content-Type' => 'text/plain'
                ],
                'Forbidden'
            );
        }

        $this->tracer->error("403 $method $path");

        return $response;
    }

    /**
     * @param string $method
     * @param string $path
     * @return \React\Http\Response
     */
    private function notFoundResponse($method, $path)
    {
        static $response;
        if (!$response) {
            $response = new \React\Http\Response(
                404,
                [
                    'Content-Type' => 'text/plain'
                ],
                'Not found'
            );
        }

        $this->tracer->error("404 $method $path");

        return $response;
    }

    /**
     * @param string $method
     * @param string $path
     * @return \React\Http\Response
     */
    private function methodNotAllowedResponse($method, $path)
    {
        static $response;
        if (!$response) {
            $response = new \React\Http\Response(
                405,
                [
                    'Content-Type' => 'text/plain'
                ],
                'Method not allowed'
            );
        }

        $this->tracer->error("405 $method $path");

        return $response;
    }
}
