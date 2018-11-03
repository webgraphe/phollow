<?php

namespace Webgraphe\Phollow;

use Ratchet\ComponentInterface;
use React\EventLoop\LoopInterface;
use Webgraphe\Phollow\Components\HttpRequestHandler;

class Application
{
    /** @var Configuration */
    private $configuration;
    /** @var LoopInterface */
    private $loop;
    /** @var string */
    private $errorLogFile;
    /** @var \React\Stream\ReadableStreamInterface */
    private $readableErrorStream;
    /** @var HttpRequestHandler */
    private $httpRequestHandler;
    /** @var Components\NotificationComponent */
    private $notificationComponent;
    /** @var \React\Socket\Server */
    private $webSocketSocket;
    /** @var \Ratchet\WebSocket\WsServer */
    private $webSocketServer;
    /** @var \React\Socket\Server */
    private $httpSocket;
    /** @var \React\Http\Server */
    private $httpServer;
    /** @var Components\WritableErrorStream */
    private $writableErrorStream;
    /** @var Tracer */
    private $tracer;

    public function __construct(Configuration $configuration = null)
    {
        $this->configuration = $configuration ?: new Configuration;
        $this->tracer = $this->configuration->getTracer();
    }

    /**
     * @param Configuration|null $configuration
     * @return static
     */
    public static function create(Configuration $configuration = null)
    {
        $application = new static($configuration);
        $application->setup();

        return $application;
    }

    public function run()
    {
        $this->tracer->setup("\rRunning loop");
        $this->tracer->notice(
            'Listening to HTTP requests on ' . str_replace('tcp:', 'http:', $this->httpSocket->getAddress())
        );
        $this->tracer->notice('Listening to WebSocket requests on ' . $this->webSocketSocket->getAddress());
        $this->tracer->setup("Webgraphe Phollow server is ready - Press CTRL-C to stop");

        $this->loop->run();
    }

    /**
     * @throws \Exception
     */
    private function setup()
    {
        $this->loop = $this->prepareLoop();

        $this->errorLogFile = $this->prepareErrorLogFile();
        $this->readableErrorStream = $this->prepareReadableErrorStream($this->loop, $this->errorLogFile);
        $this->writableErrorStream = $this->prepareWritableErrorStream();
        $this->pipeErrorStreams($this->readableErrorStream, $this->writableErrorStream);

        $this->httpSocket = $this->prepareHttpSocket($this->loop, $this->configuration->getHttpPort());
        $this->httpRequestHandler = $this->prepareHttpRequestHandler($this->configuration->getOrigin());
        $this->httpServer = $this->prepareHttpServer($this->httpSocket, $this->httpRequestHandler);

        $this->notificationComponent = $this->prepareNotificationComponent();
        $this->webSocketSocket = $this->prepareWebSocketSocket($this->loop, $this->configuration->getWebSocketPort());
        $this->webSocketServer = $this->prepareWebSocketServer(
            $this->webSocketSocket,
            $this->loop,
            $this->notificationComponent,
            $this->configuration->getOrigin()
        );
    }

    private function tearDown()
    {
        $this->tracer->setup("\rStopping loop");
        $this->loop and $this->loop->stop();

        $this->tracer->setup("\rClosing streams");
        $this->readableErrorStream and $this->readableErrorStream->close();
        $this->writableErrorStream and $this->writableErrorStream->close();
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function prepareErrorLogFile()
    {
        $errorLogFile = $this->configuration->getErrorLogFile();
        $this->tracer->setup("Preparing error file $errorLogFile");

        $this->unlinkFile($errorLogFile);
        $this->touchFile($errorLogFile);
        $this->makeFileWritableToEveryone($errorLogFile);

        return $errorLogFile;
    }

    /**
     * @param LoopInterface $loop
     * @param string $errorLogFile
     * @return \React\Stream\ReadableStreamInterface
     */
    private function prepareReadableErrorStream(LoopInterface $loop, $errorLogFile)
    {
        $errorLogFile = escapeshellarg($errorLogFile);
        $command = "tail -f -n 0 $errorLogFile 2>&1";
        $this->tracer->setup("Preparing readable error stream; $command");

        return new \React\Stream\ReadableResourceStream(popen($command, 'r'), $loop);
    }

    /**
     * @param string $errorLogFile
     * @throws \Exception
     */
    private function unlinkFile($errorLogFile)
    {
        if (file_exists($errorLogFile) && !@unlink($errorLogFile)) {
            throw new \Exception("Can't unlink $errorLogFile");
        }
    }

    /**
     * @param string $errorLogFile
     * @throws \Exception
     */
    private function touchFile($errorLogFile)
    {
        if (!@touch($errorLogFile)) {
            throw new \Exception("Can't touch $errorLogFile");
        }
    }

    /**
     * @param $errorLogFile
     * @throws \Exception
     */
    private function makeFileWritableToEveryone($errorLogFile)
    {
        if (!@chmod($errorLogFile, 0666)) {
            throw new \Exception("Can't make $errorLogFile writable to everyone");
        }
    }

    /**
     * @param \React\Socket\Server $httpSocket
     * @param HttpRequestHandler $httpRequestHandler
     * @return \React\Http\Server
     */
    private function prepareHttpServer(\React\Socket\Server $httpSocket, HttpRequestHandler $httpRequestHandler)
    {
        $this->tracer->setup("Preparing HTTP server");
        $httpServer = new \React\Http\Server($httpRequestHandler);
        $httpServer->listen($httpSocket);

        return $httpServer;
    }

    /**
     * @return Components\NotificationComponent
     */
    private function prepareNotificationComponent()
    {
        $this->tracer->setup("Preparing notification component");

        return new Components\NotificationComponent($this->tracer->withComponent('WSCK'));
    }

    /**
     * @param \React\Socket\Server $webSocketSocket
     * @param LoopInterface $loop
     * @param ComponentInterface $notificationComponent
     * @param string $origin
     * @return \Ratchet\Server\IoServer
     */
    private function prepareWebSocketServer(
        \React\Socket\Server $webSocketSocket,
        LoopInterface $loop,
        ComponentInterface $notificationComponent,
        $origin = ''
    ) {
        $this->tracer->setup("Preparing WebSocket server");

        $wsServer = new \Ratchet\WebSocket\WsServer($notificationComponent);
        if ($origin) {
            $wsServer = new \Ratchet\Http\OriginCheck($wsServer, ['localhost']);
            $wsServer->allowedOrigins[] = $origin;
        }
        $webSocketServer = new \Ratchet\Server\IoServer(
            new \Ratchet\Http\HttpServer($wsServer),
            $webSocketSocket,
            $loop
        );

        return $webSocketServer;
    }

    /**
     * @return LoopInterface
     */
    private function prepareLoop()
    {
        $this->tracer->setup("Preparing loop");

        $loop = \React\EventLoop\Factory::create();
        $loop->addSignal(
            SIGINT,
            function () {
                $this->tearDown();
            }
        );

        return $loop;
    }

    private function prepareWritableErrorStream()
    {
        $this->tracer->setup("Preparing writable error stream; " . Components\WritableErrorStream::class);

        return new Components\WritableErrorStream($this->tracer->withComponent('PHPE'));
    }

    private function pipeErrorStreams(
        \React\Stream\ReadableStreamInterface $readableErrorStream,
        \React\Stream\WritableStreamInterface $writableErrorStream
    ) {
        $readableErrorStream->pipe($writableErrorStream);
    }

    /**
     * @param LoopInterface $loop
     * @param int $port
     * @return \React\Socket\Server
     */
    private function prepareHttpSocket(LoopInterface $loop, $port)
    {
        return new \React\Socket\Server("0.0.0.0:$port", $loop);
    }

    /**
     * @param LoopInterface $loop
     * @param int $port
     * @return \React\Socket\Server
     */
    private function prepareWebSocketSocket(LoopInterface $loop, $port)
    {
        return new \React\Socket\Server("0.0.0.0:$port", $loop);
    }

    /**
     * @param string $origin
     * @return HttpRequestHandler
     */
    private function prepareHttpRequestHandler($origin = '')
    {
        return new HttpRequestHandler($this->tracer->withComponent('HTTP'), $origin);
    }
}
