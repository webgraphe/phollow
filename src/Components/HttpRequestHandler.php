<?php

namespace Webgraphe\Phollow\Components;

use Webgraphe\Phollow\Application;
use Webgraphe\Phollow\Documents\DocumentCollection;
use Webgraphe\Phollow\Tracer;

class HttpRequestHandler
{
    /** @var string */
    const DOCUMENT_ROOT = __DIR__ . '/../../resources/public';

    /**
     * @var string[]
     * @see https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types
     */
    const MIME_TYPES = [
        'css' => 'text/css',
        'htm' => 'text/html',
        'html' => 'text/html',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
    ];

    /** @var DocumentCollection */
    private $documentCollection;
    /** @var Tracer */
    private $tracer;
    /** @var \FastRoute\Dispatcher */
    private $dispatcher;
    /** @var string */
    private $origin;
    /** @var string */
    private $documentRoot;

    /**
     * @param DocumentCollection $documents
     * @param Tracer $tracer
     * @param string $documentRoot
     * @param string $origin
     */
    protected function __construct(DocumentCollection $documents, Tracer $tracer, $documentRoot, $origin = '')
    {
        $this->documentCollection = $documents;
        $this->documentRoot = $documentRoot;
        $this->tracer = $tracer;
        $this->origin = $origin;
        $this->dispatcher = \FastRoute\simpleDispatcher(
            function (\FastRoute\RouteCollector $routes) {
                $routes->addGroup(
                    '/data',
                    function (\FastRoute\RouteCollector $routes) {
                        $routes->get('/meta', $this->getMeta());
                        $routes->get('/documents[/{id:\d+}]', $this->getDataDocuments());
                        $routes->delete('/scripts/{id:\d+}', $this->deleteDataScript());
                    }
                );
            }
        );
    }

    /**
     * @param DocumentCollection $documents
     * @param Tracer $tracer
     * @param string $origin
     * @return static
     * @throws \Exception
     */
    public static function create(DocumentCollection $documents, Tracer $tracer, $origin = '')
    {
        $documentRoot = realpath(self::DOCUMENT_ROOT);
        if (!$documentRoot) {
            throw new \Exception("Can't resolve document root " . self::DOCUMENT_ROOT);
        }

        $instance = new static($documents, $tracer, $documentRoot, $origin);

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
                return $this->serveHttpResponse($request, $this->getPublicFileResponse($path, $request));
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                return $this->serveHttpResponse($request, $this->methodNotAllowedResponse($method, $path));
            default:
                return $this->serveHttpResponse($request, $routeInfo[1]($request, ...array_values($routeInfo[2])));
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
                        return self::cachedHttpResponse(304, null, ['ETag' => '"' . $sha1 . '"']);
                    }
                }

                return self::cachedHttpResponse(
                    200,
                    $body,
                    [
                        'Content-Type' => $contentType,
                        'ETag' => '"' . $sha1 . '"'
                    ]
                );
            }

            return self::httpResponse(415, "Unsupported media type '$contentType'");
        }

        return self::httpResponse(404, "$publicFilePath not found");
    }

    /**
     * @param int $statusCode
     * @param \JsonSerializable|mixed $data Data to encode as JSON
     * @param array $headers
     * @return \React\Http\Response
     */
    private static function jsonResponse($statusCode, $data = null, array $headers = [])
    {
        return static::httpResponse(
            $statusCode,
            null !== $data ? json_encode($data) : null,
            array_merge($headers, ['Content-Type' => 'application/json'])
        );
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

        return array_key_exists($extension, self::MIME_TYPES) ? self::MIME_TYPES[$extension] : null;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \React\Http\Response $response
     * @return \React\Http\Response
     */
    private function serveHttpResponse(
        \Psr\Http\Message\ServerRequestInterface $request,
        \React\Http\Response $response
    ) {
        $code = $response->getStatusCode();
        $ip = $request->getServerParams()['REMOTE_ADDR'];
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $size = $response->getBody()->getSize();
        $message = "$ip \"$method $path\" $code $size";

        if ($code - 200 < 100) {
            $this->tracer->notice($message);
        } elseif ($code - 300 < 100) {
            $this->tracer->warning($message);
        } else {
            $this->tracer->error($message);
        }

        return $response;
    }

    /**
     * @return \Closure
     */
    private function getDataDocuments()
    {
        return function (
            /** @noinspection PhpUnusedParameterInspection */
            \Psr\Http\Message\ServerRequestInterface $request,
            $id = null
        ) {
            if (null === $id) {
                return static::jsonResponse(200, ['data' => $this->documentCollection->getDocuments()]);
            }

            if ($document = $this->documentCollection->getDocument($id)) {
                return static::jsonResponse(200, $document);
            }

            return static::jsonResponse(404, []);
        };
    }

    private function deleteDataScript()
    {
        return function (
            /** @noinspection PhpUnusedParameterInspection */
            \Psr\Http\Message\ServerRequestInterface $request,
            $id = null
        ) {
            if ($forgotten = $this->documentCollection->forgetScript($id)) {
                return static::jsonResponse(
                    200,
                    [
                        'meta' => [
                            'method' => $request->getMethod(),
                            'path' => $request->getUri()->getPath(),
                        ],
                        'data' => [
                            'scriptId' => $id,
                            'documents' => [
                                'forgotten' => $forgotten
                            ]
                        ]
                    ]
                );
            }

            return static::jsonResponse(404, []);
        };
    }

    /**
     * @return \Closure
     */
    private function getMeta()
    {
        return function (\Psr\Http\Message\ServerRequestInterface $request) {
            return static::jsonResponse(
                200,
                [
                    'data' => [
                        'application' => Application::getInstance()->getMeta($request->getUri()->getHost())
                    ]
                ]
            );
        };
    }
}
