<?php

namespace Webgraphe\Phollow;

use Webgraphe\Phollow\Contracts\ErrorFilterContract;
use Webgraphe\Phollow\Documents\Error;

class ErrorHandler
{
    /** @var string|bool|null */
    private $logFile;
    /** @var string */
    private $id;
    /** @var ErrorFilterContract|callable */
    private $errorFilter;
    /** @var string|null */
    private $basePath;
    /** @var resource|bool */
    private $socket;
    /** @var string */
    private $applicationName;
    /** @var string */
    private $hostName;
    /** @var string */
    private $errorReporting;

    /**
     * @param string $logFile
     */
    protected function __construct($logFile)
    {
        $this->id = sha1(uniqid(getmypid(), true));
        $this->logFile = $logFile;
        if ($this->logFile && file_exists($this->logFile)) {
            $this->socket = @stream_socket_client("unix://$logFile", $errno, $errstr, null);
            if (!$this->socket) {
                trigger_error("Can't create socket for ErrorHandler; ($errno) $errstr");
            }
        }
        $this->hostName = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'CLI';
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
     * @param string $logFile
     * @return static
     */
    public static function create($logFile)
    {
        return new static($logFile);
    }

    public function register()
    {
        if ($this->socket) {
            if (null !== $this->errorReporting) {
                error_reporting($this->errorReporting);
                ini_set('display_errors', (int)$this->errorReporting);
            }
            set_error_handler(
                function () {
                    return $this(...func_get_args());
                }
            );
            register_shutdown_function(
                function () {
                    if ($data = error_get_last()) {
                        return $this->__invoke($data['type'], $data['message'], $data['file'], $data['line']);
                    }

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

        $json = json_encode(
            $error->withSessionId($this->getId())
                ->withApplicationName($this->applicationName)
                ->withHostName($this->hostName)
        );

        return (bool)fwrite($this->socket, $json . PHP_EOL);
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
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
}
