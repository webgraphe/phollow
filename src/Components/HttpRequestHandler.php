<?php

namespace Webgraphe\Phollow\Components;

use Webgraphe\Phollow\Tracer;

class HttpRequestHandler
{
    const DOCUMENT_ROOT = __DIR__ . '/../../resources/public';

    const MIME_TYPES = [
        'css' => 'text/css',
        'htm' => 'text/html',
        'html' => 'text/html',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'map' => 'text/plain'
    ];

    /** @var Tracer */
    private $tracer;
    /** @var \FastRoute\Dispatcher */
    private $dispatcher;
    /** @var string */
    private $origin;
    /** @var string */
    private $documentRoot;

    /**
     * @param Tracer $tracer
     * @param string $documentRoot
     * @param string $origin
     */
    protected function __construct(Tracer $tracer, $documentRoot, $origin = '')
    {
        $this->documentRoot = $documentRoot;
        $this->tracer = $tracer;
        $this->origin = $origin;
        $this->dispatcher = \FastRoute\simpleDispatcher(
            function (\FastRoute\RouteCollector $routes) {
                // TODO Add data routes
            }
        );
    }

    /**
     * @param Tracer $tracer
     * @param string $origin
     * @return static
     * @throws \Exception
     */
    public static function create(Tracer $tracer, $origin = '')
    {
        $documentRoot = realpath(self::DOCUMENT_ROOT);
        if (!$documentRoot) {
            throw new \Exception("Can't resolve document root " . self::DOCUMENT_ROOT);
        }

        $instance = new static($tracer, $documentRoot, $origin);

        return $instance;
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
                return $this->serveHttpResponse($method, $path, $this->getPublicFileResponse($path, $request));
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                return $this->serveHttpResponse($method, $path, $this->methodNotAllowedResponse($method, $path));
            case \FastRoute\Dispatcher::FOUND:
            default:
                return $this->serveHttpResponse(
                    $method,
                    $path,
                    $routeInfo[1]($request, ... array_values($routeInfo[2]))
                );
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
            $response = self::httpResponse(403, 'Forbidden');
        }

        $this->tracer->error("403 $method $path");

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
            $response = self::httpResponse(405, 'Method not allowed');
        }

        $this->tracer->error("405 $method $path");

        return $response;
    }

    /**
     * @param string $path
     * @return string|null
     */
    private function resolvePublicFilePath($path)
    {
        $rawPath = $this->documentRoot . $path;
        if (!file_exists($rawPath)) {
            return null;
        }

        $realPath = realpath($rawPath);

        if (0 !== strpos($realPath, $this->documentRoot)) {
            return null;
        }

        if (is_dir($realPath)) {
            return $this->resolvePublicFilePath($path . '/index.html');
        }

        return $realPath;
    }

    /**
     * @param string $publicFilePath
     * @param \Psr\Http\Message\ServerRequestInterface|null $request
     * @return \React\Http\Response
     */
    private function getPublicFileResponse($publicFilePath, \Psr\Http\Message\ServerRequestInterface $request = null)
    {
        if ($fullPath = $this->resolvePublicFilePath($publicFilePath)) {
            if ($contentType = $this->resolveContentType($fullPath)) {
                $body = file_get_contents($fullPath);
                $sha1 = sha1($body);
                if ($header = $request->getHeader('If-None-Match')) {
                    if (str_replace('"', '', $header[0]) === $sha1) {
                        return self::cachedHttpResponse(304, null, ['ETag' => '"' . $sha1 .'"']);
                    }
                }

                return self::cachedHttpResponse(
                    200,
                    $body,
                    [
                        'Content-Type' => $contentType,
                        'ETag' => '"' . $sha1 .'"'
                    ]
                );
            }

            return self::httpResponse(415, "Unsupported media type '$contentType'");
        }

        return self::httpResponse(404, "$publicFilePath not found");
    }

    /**
     * @param int $statusCode
     * @param string $body
     * @param array $headers
     * @return \React\Http\Response
     */
    private static function httpResponse($statusCode, $body = null, array $headers = [])
    {
        return new \React\Http\Response($statusCode, array_merge(['Content-Type' => 'text/plain'], $headers), $body);
    }

    /**
     * @param int $statusCode
     * @param string $body
     * @param array $headers
     * @return \React\Http\Response
     */
    private static function cachedHttpResponse($statusCode, $body = null, array $headers = [])
    {
        return static::httpResponse($statusCode, $body, array_merge(['Cache-Control' => 'max-age=3600'], $headers));
    }

    /**
     * @param string $publicFilePath
     * @return string|null
     */
    private function resolveContentType($publicFilePath)
    {
        $extension = strtolower(substr($publicFilePath, strrpos($publicFilePath, '.') + 1));

        return isset(self::MIME_TYPES[$extension]) ? self::MIME_TYPES[$extension] : null;
    }

    /**
     * @param string $method
     * @param string $path
     * @param \React\Http\Response $response
     * @return \React\Http\Response
     */
    private function serveHttpResponse($method, $path, \React\Http\Response $response)
    {
        $code = $response->getStatusCode();
        $message = "$code $method $path";

        if ($code - 200 < 100) {
            $this->tracer->info($message);
        } elseif ($code - 300 < 100) {
            $this->tracer->warning($message);
        } else {
            $this->tracer->error($message);
        }

        return $response;
    }
}
