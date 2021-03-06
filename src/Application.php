<?php

namespace Webgraphe\Phollow;

use Ratchet\ComponentInterface;
use React\EventLoop\LoopInterface;
use Webgraphe\Phollow\Components\HttpRequestHandler;
use Webgraphe\Phollow\Components\LogComponent;
use Webgraphe\Phollow\Documents;

class Application
{
    /** @var string */
    const VERSION = '0.2.4';
    /** @var string */
    const DEFAULT_ROUTE_IP = '0.0.0.0';

    /** @var static */
    private static $instance;

    /** @var Configuration */
    private $configuration;
    /** @var LoopInterface */
    private $loop;
    /** @var \React\Stream\ReadableStreamInterface */
    private $readableLogStream;
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
    /** @var Tracer */
    private $tracer;
    /** @var Components\LogComponent */
    private $logComponent;
    /** @var \React\Socket\Server */
    private $logSocket;
    /** @var \Ratchet\Server\IoServer */
    private $logServer;
    /** @var Documents\DocumentCollection */
    private $documentCollection;
    /** @var float */
    private $startTime;

    /**
     * @return string
     */
    public static function usage()
    {
        $version = self::VERSION;
        $usage = <<<USAGE
    ____  __          ____             
   / __ \/ /_  ____  / / /___ _      __
  / /_/ / __ \/ __ \/ / / __ \ | /| / /
 / ____/ / / / /_/ / / / /_/ / |/ |/ / 
/_/   /_/ /_/\____/_/_/\____/|__/|__/  

Version $version by Jean-Philippe Léveillé

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
        $this->startTime = microtime(true);
        $this->configuration = $configuration ?: new Configuration;
        $this->tracer = $this->configuration->getTracer();
        $this->documentCollection = Documents\DocumentCollection::create();
    }

    /**
     * @param Configuration|null $configuration
     * @return static
     */
    public static function create(Configuration $configuration = null)
    {
        $application = new static($configuration);
        $application->setup();

        static::$instance = $application;

        return $application;
    }

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (!static::$instance) {
            static::$instance = new static;
        }

        return static::$instance;
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
        $this->tracer->setup("Webgraphe Phollow " . self::VERSION . " by Jean-Philippe Léveillé");
        $this->tracer->setup($this->configuration->getSummary());
        $this->tracer->info(str_replace(PHP_EOL, PHP_EOL . '  -', '  -' . $this->configuration->toIni()));

        $this->loop = $this->prepareLoop();

        $this->notificationComponent = $this->prepareNotificationComponent();
        $this->webSocketSocket = $this->prepareWebSocketSocket($this->loop, $this->configuration->getWebSocketPort());
        $this->webSocketServer = $this->prepareWebSocketServer(
            $this->webSocketSocket,
            $this->loop,
            $this->notificationComponent,
            $this->configuration->getServerOrigin()
        );

        $this->logComponent = $this->prepareLogComponent();
        $this->logSocket = $this->prepareLogSocket($this->loop, $this->configuration->getLogFile());
        $this->logServer = $this->prepareLogServer(
            $this->logSocket,
            $this->loop,
            $this->logComponent
        );
        $this->logComponent->onNewDocument(
            function (Document $document) {
                switch (true) {
                    case $document instanceof Documents\ConnectionOpened:
                        $this->documentCollection->openConnection($document);
                        break;
                    case $document instanceof Documents\ScriptStarted:
                        $this->documentCollection->startScript($document);
                        break;
                    case $document instanceof Documents\Error:
                        $this->documentCollection->addError($document);
                        break;
                    case $document instanceof Documents\ScriptEnded:
                        $this->documentCollection->endScript($document);
                        break;
                    case $document instanceof Documents\ConnectionClosed:
                        $this->documentCollection->closeConnection($document);
                        break;
                    default:
                        $this->tracer->warning("Unhandled document type '{$document->getDocumentType()}'");

                        return false;
                }

                $this->notificationComponent->notifyNewDocument($document);

                return true;
            }
        );

        $this->httpSocket = $this->prepareHttpSocket($this->loop, $this->configuration->getHttpPort());
        $this->httpRequestHandler = $this->prepareHttpRequestHandler(
            $this->documentCollection,
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
        $this->readableLogStream and $this->readableLogStream->close();

        $this->tracer->setup("Flushing log file");
        $logFile = $this->configuration->getLogFile();
        $this->unlinkFile($logFile);
    }

    /**
     * @param string $file
     * @throws \Exception
     */
    private function unlinkFile($file)
    {
        if (file_exists($file) && !@unlink($file)) {
            throw new \Exception("Can't unlink $file");
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
        $replace = [
            'http://',
            $origin ?: self::DEFAULT_ROUTE_IP
        ];
        $this->tracer->notice(
            'Listening to HTTP requests on ' . str_replace($search, $replace, $httpSocket->getAddress())
        );

        return $httpServer;
    }

    /**
     * @return Components\LogComponent
     */
    private function prepareLogComponent()
    {
        $this->tracer->setup("Preparing Log component");

        return new Components\LogComponent($this->tracer->withComponent('LOGC'));
    }

    /**
     * @return Components\NotificationComponent
     */
    private function prepareNotificationComponent()
    {
        $this->tracer->setup("Preparing Notification component");

        return new Components\NotificationComponent($this->tracer->withComponent('WSOC'));
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
        $this->tracer->setup("Preparing Loop");

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
     * @param LoopInterface $loop
     * @param int $port
     * @return \React\Socket\Server
     */
    private function prepareHttpSocket(LoopInterface $loop, $port)
    {
        $this->tracer->setup("Preparing HTTP socket");

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
        $this->tracer->setup("Preparing WebSocket socket");

        $host = self::DEFAULT_ROUTE_IP;

        return new \React\Socket\Server("$host:$port", $loop);
    }

    /**
     * @param Documents\DocumentCollection $documents
     * @param string $origin
     * @return HttpRequestHandler
     * @throws \Exception
     */
    private function prepareHttpRequestHandler(Documents\DocumentCollection $documents, $origin = '')
    {
        $this->tracer->setup("Preparing HTTP request handler");

        return HttpRequestHandler::create($documents, $this->tracer->withComponent('HTTP'), $origin);
    }

    /**
     * @param string $host
     * @return array
     */
    public function getMeta($host)
    {
        return [
            'documents' => [
                'count' => count($this->documentCollection),
            ],
            'server' => [
                'info' => [
                    'time' => microtime(true) - $this->startTime,
                    'memory' => [
                        'current' => memory_get_usage(true),
                        'peak' => memory_get_peak_usage(true)
                    ]
                ],
                'websocket' => [
                    'host' => $host,
                    'port' => $this->configuration->getWebSocketPort()
                ]
            ]
        ];
    }

    /**
     * @param LoopInterface $loop
     * @param string $logFile
     * @return \React\Socket\UnixServer
     * @throws \Exception
     */
    private function prepareLogSocket(LoopInterface $loop, $logFile)
    {
        $this->tracer->setup("Preparing Log socket");

        $this->unlinkFile($logFile);
        $socket = new \React\Socket\UnixServer($logFile, $loop);
        $oldMask = umask(0777);
        chmod($logFile, 0666);
        umask($oldMask);

        return $socket;
    }

    /**
     * @param \React\Socket\UnixServer $logSocket
     * @param $loop
     * @param $logComponent
     * @return mixed
     */
    private function prepareLogServer(
        \React\Socket\UnixServer $logSocket,
        LoopInterface $loop,
        LogComponent $logComponent
    ) {
        $this->tracer->setup("Preparing Log server");

        $logServer = new \Ratchet\Server\IoServer($logComponent, $logSocket, $loop);

        $this->tracer->notice(
            'Listening to Documents pushed on ' . $logSocket->getAddress()
        );

        return $logServer;
    }
}
