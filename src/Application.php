<?php

namespace Webgraphe\Phollow;

use Ratchet\ComponentInterface;
use React\EventLoop\LoopInterface;
use Webgraphe\Phollow\Components\HttpRequestHandler;
use Webgraphe\Phollow\Contracts\ErrorCollectionContract;
use Webgraphe\Phollow\Documents\ErrorCollection;

class Application
{
    const DEFAULT_ROUTE_IP = '0.0.0.0';

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
    /** @var ErrorCollection */
    private $errorCollection;

    public static function usage()
    {
        $usage = <<<USAGE
    ____  __          ____             
   / __ \/ /_  ____  / / /___ _      __
  / /_/ / __ \/ __ \/ / / __ \ | /| / /
 / ____/ / / / /_/ / / / /_/ / |/ |/ / 
/_/   /_/ /_/\____/_/_/\____/|__/|__/  

Version 1.0.0

Usage:
    phollow COMMAND [OPTIONS] [ARGUMENTS]

  Available commands:
    help ···················· Displays usage information
    generate-configuration ·· Generates an INI configuration (supports option overrides)
    run ····················· Runs the Webgraphe Phollow server

  Options:
    Global:
      --configuration-file=FILE ·· Loads configuration from given INI file (default=phollow.ini)
      --no-configuration ········· Skips loading configuration file
    Command options:

USAGE;
        $maxLength = 0;
        array_map(
            function ($key) use (&$maxLength) {
                $maxLength = max($maxLength, strlen($key));
            },
            array_keys(Configuration::SETTING_DESCRIPTIONS)
        );
        foreach (Configuration::SETTING_DESCRIPTIONS as $key => $value) {
            $usage .= '      --' . $key . ' ··' . str_repeat('·', $maxLength - strlen($key)) . ' ' . $value . PHP_EOL;
        }

        return $usage . PHP_EOL;
    }

    public function __construct(Configuration $configuration = null)
    {
        $this->configuration = $configuration ?: new Configuration;
        $this->tracer = $this->configuration->getTracer();
        $this->errorCollection = new ErrorCollection;
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

    /**
     * @return int
     */
    public function run()
    {
        $this->tracer->setup("Webgraphe Phollow server is ready - Press CTRL-C to stop");
        $this->loop->run();

        return 0;
    }

    /**
     * @throws \Exception
     */
    private function setup()
    {
        $this->tracer->setup($this->configuration->getSummary());
        $this->tracer->info(str_replace(PHP_EOL, PHP_EOL . '  ', '  ' . $this->configuration->toIni()));

        $this->loop = $this->prepareLoop();

        $this->errorLogFile = $this->prepareErrorLogFile();
        $this->readableErrorStream = $this->prepareReadableErrorStream($this->loop, $this->errorLogFile);
        $this->writableErrorStream = $this->prepareWritableErrorStream();
        $this->pipeErrorStreams($this->readableErrorStream, $this->writableErrorStream);

        $this->notificationComponent = $this->prepareNotificationComponent();
        $this->webSocketSocket = $this->prepareWebSocketSocket($this->loop, $this->configuration->getWebSocketPort());
        $this->webSocketServer = $this->prepareWebSocketServer(
            $this->webSocketSocket,
            $this->loop,
            $this->notificationComponent,
            $this->configuration->getServerOrigin()
        );

        $this->writableErrorStream->onNewError(
            function (Documents\Error $error) {
                $this->errorCollection->addError($error);
                $this->notificationComponent->notifyNewError($error);
            }
        );

        $this->httpSocket = $this->prepareHttpSocket($this->loop, $this->configuration->getHttpPort());
        $this->httpRequestHandler = $this->prepareHttpRequestHandler(
            $this->errorCollection,
            $this->configuration->getServerOrigin()
        );
        $this->httpServer = $this->prepareHttpServer(
            $this->httpSocket,
            $this->httpRequestHandler,
            $this->configuration->getServerOrigin()
        );
    }

    /**
     * @param int|null $signal
     * @throws \Exception
     */
    private function tearDown($signal = null)
    {
        if ($signal) {
            $this->tracer->notice("\rProcess control signal ($signal) captured");
        }

        $this->tracer->warning("\rShutting down");

        $this->tracer->setup("Stopping loop");
        $this->loop and $this->loop->stop();

        $this->tracer->setup("Closing streams");
        $this->readableErrorStream and $this->readableErrorStream->close();
        $this->writableErrorStream and $this->writableErrorStream->close();

        $this->tracer->setup("Flushing log file");
        $errorLogFile = $this->configuration->getErrorLogFile();
        $this->unlinkFile($errorLogFile);
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
     * @param string null $origin
     * @return \React\Http\Server
     */
    private function prepareHttpServer(
        \React\Socket\Server $httpSocket,
        HttpRequestHandler $httpRequestHandler,
        $origin = null
    ) {
        $this->tracer->setup("Preparing HTTP server");
        $httpServer = new \React\Http\Server($httpRequestHandler);
        $httpServer->listen($httpSocket);

        $search = [
            'tcp://',
            self::DEFAULT_ROUTE_IP,
        ];
        $replace =[
            'http://',
            $origin ?: self::DEFAULT_ROUTE_IP
        ];
        $this->tracer->notice(
            'Listening to HTTP requests on ' . str_replace($search, $replace, $httpSocket->getAddress())
        );

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

        $search = self::DEFAULT_ROUTE_IP;
        $replace = $origin ?: self::DEFAULT_ROUTE_IP;
        $this->tracer->notice(
            'Listening to WebSocket requests on ' . str_replace($search, $replace, $webSocketSocket->getAddress())
        );

        return $webSocketServer;
    }

    /**
     * @return LoopInterface
     */
    private function prepareLoop()
    {
        $this->tracer->setup("Preparing loop");

        $tearDown = function () {
            $this->tearDown(...func_get_args());
        };

        $loop = \React\EventLoop\Factory::create();
        if (extension_loaded('pcntl')) {
            $loop->addSignal(constant('SIGINT'), $tearDown);
            $loop->addSignal(constant('SIGTERM'), $tearDown);
        }

        return $loop;
    }

    /**
     * @return Components\WritableErrorStream
     */
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
        $host = self::DEFAULT_ROUTE_IP;

        return new \React\Socket\Server("$host:$port", $loop);
    }

    /**
     * @param LoopInterface $loop
     * @param int $port
     * @return \React\Socket\Server
     */
    private function prepareWebSocketSocket(LoopInterface $loop, $port)
    {
        $host = self::DEFAULT_ROUTE_IP;

        return new \React\Socket\Server("$host:$port", $loop);
    }

    /**
     * @param ErrorCollectionContract $errorCollection
     * @param string $origin
     * @return HttpRequestHandler
     * @throws \Exception
     */
    private function prepareHttpRequestHandler(ErrorCollectionContract $errorCollection, $origin = '')
    {
        return HttpRequestHandler::create($errorCollection, $this->tracer->withComponent('HTTP'), $origin);
    }
}
