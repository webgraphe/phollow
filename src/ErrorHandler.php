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

    /**
     * @param string $logFile
     */
    protected function __construct($logFile)
    {
        $this->id = sha1(uniqid(getmypid(), true));
        $this->logFile = $logFile;
    }

    /**
     * @param string $logFile
     * @return static
     */
    public static function create($logFile)
    {
        if (!file_exists($logFile)) {
            trigger_error("Log file '$logFile' not found", E_USER_WARNING);
            $logFile = false;
        } elseif (!is_writable($logFile)) {
            trigger_error("Log file '$logFile' is not writable", E_USER_WARNING);
            $logFile = false;
        } else {
            $logFile = realpath($logFile);
        }

        return new static($logFile);
    }

    public function register()
    {
        if ($this->logFile) {
            set_error_handler($this);
            register_shutdown_function(
                function () {
                    if ($data = error_get_last()) {
                        call_user_func($this, $data['type'], $data['message'], $data['file'], $data['line']);
                    }
                }
            );
        }
    }

    /**
     * @param int $severity
     * @param string $message
     * @param string $file
     * @param string $line
     * @return bool
     */
    public function __invoke($severity, $message, $file, $line)
    {
        if (0 === (error_reporting() & $severity)) {
            return false;
        }

        if (!$this->logFile) {
            return false;
        }

        $error = Error::create(
            $message,
            $severity,
            $file,
            $line,
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            $this->basePath
        );

        if (!$this->filterError($error)) {
            return false;
        }

        $json = json_encode($error->withSessionId($this->getId()));
        if (!@file_put_contents($this->logFile, $json . PHP_EOL, FILE_APPEND)) {
            $this->logFile = false;

            return false;
        }

        return true;
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
     * @param bool $displayErrors
     * @return static
     */
    public function withErrorReporting($errorReporting, $displayErrors = false)
    {
        error_reporting($errorReporting);
        ini_set('display_errors', $displayErrors);

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
     * @param Error $error
     * @return bool
     */
    private function filterError(Error $error)
    {
        return $this->errorFilter ? (bool)call_user_func($this->errorFilter, $error) : true;
    }
}
