<?php

namespace Webgraphe\Phollow;

use Webgraphe\Phollow\Contracts\ErrorFilterContract;
use Webgraphe\Phollow\Documents\Error;
use Webgraphe\Phollow\Documents\ScriptEnded;
use Webgraphe\Phollow\Documents\ScriptStarted;

class ErrorHandler
{
    /** @var ErrorFilterContract|callable */
    private $errorFilter;
    /** @var string|null */
    private $basePath;
    /** @var resource|bool */
    private $socket;
    /** @var string */
    private $applicationName;
    /** @var string */
    private $errorReporting;
    /** @var Configuration */
    private $configuration;
    /** @var ScriptStarted */
    private $scriptStart;
    /** @var \Closure|null */
    private $errorHandler;
    /** @var \Closure|null */
    private $shutdownFunction;
    /** @var float */
    private $startTime;

    /**
     * @param Configuration $configuration
     */
    protected function __construct(Configuration $configuration)
    {
        $this->startTime = microtime(true);
        $this->scriptStart = ScriptStarted::fromGlobals();
        $this->configuration = $configuration;
        $logFile = $configuration->getLogFile();
        if ($logFile && file_exists($logFile)) {
            $this->socket = @stream_socket_client("unix://$logFile", $errno, $errstr, null);
            if (!$this->socket) {
                trigger_error("Can't create socket for ErrorHandler; ($errno) $errstr");
            }
        } else {
            trigger_error("Log file '$logFile' not found");
        }
        $this->applicationName = 'PHP Application';
    }

    public function __destruct()
    {
        if ($this->socket) {
            fclose($this->socket);
        }

        $this->socket = null;
    }

    /**
     * @param Configuration|null $configuration
     * @return static
     * @throws \Exception
     */
    public static function create(Configuration $configuration = null)
    {
        return new static($configuration ?: Configuration::fromGlobals());
    }

    /**
     * @return bool
     */
    public function register()
    {
        if ($this->socket) {
            $this->writeToSocket(
                $this->scriptStart->withApplicationName($this->applicationName)
            );

            if (null !== $this->errorReporting) {
                error_reporting($this->errorReporting);
                ini_set('display_errors', 0);
            }
            set_error_handler(
                $this->errorHandler = function () {
                    return $this(...func_get_args());
                }
            );
            register_shutdown_function(
                $this->shutdownFunction = function () {
                    if ($data = error_get_last()) {
                        $this->__invoke($data['type'], "LAST: " . $data['message'], $data['file'], $data['line']);
                    }

                    $this->writeToSocket(ScriptEnded::fromGlobal($this->startTime));

                    return false;
                }
            );

            return true;
        }

        return false;
    }

    /**
     * @param int $severityId
     * @param string $message
     * @param string $file
     * @param string $line
     * @return bool
     */
    public function __invoke($severityId, $message, $file, $line)
    {
        if (0 === (error_reporting() & $severityId)) {
            return false;
        }

        if (!$this->socket) {
            return false;
        }

        $error = Error::create(
            $message,
            $severityId,
            $file,
            $line,
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            $this->basePath
        );

        if (!$this->filterError($error)) {
            return false;
        }

        return (bool)$this->writeToSocket($error);
    }

    /**
     * @param int $errorReporting
     * @return static
     */
    public function withErrorReporting($errorReporting)
    {
        $this->errorReporting = $errorReporting;

        return $this;
    }

    /**
     * @param ErrorFilterContract|callable $filter
     * @return static
     */
    public function withErrorFilter(callable $filter)
    {
        $this->errorFilter = $filter;

        return $this;
    }

    /**
     * @param string $basePath
     * @return static
     */
    public function withBasePath($basePath)
    {
        $this->basePath = realpath($basePath);

        return $this;
    }

    /**
     * @param string $name
     * @return static
     */
    public function withApplicationName($name)
    {
        $this->scriptStart->withApplicationName($name);
        $this->applicationName = $name;

        return $this;
    }

    /**
     * @param Error $error
     * @return bool
     */
    private function filterError(Error $error)
    {
        return $this->errorFilter ? (bool)call_user_func($this->errorFilter, $error) : true;
    }

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @param mixed|\JsonSerializable $data
     * @return bool|int
     */
    private function writeToSocket($data)
    {
        return fwrite($this->socket, json_encode($data). PHP_EOL);
    }
}
