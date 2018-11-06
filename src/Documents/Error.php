<?php

namespace Webgraphe\Phollow\Documents;

use Webgraphe\Phollow\Contracts\ErrorContract;

class Error implements ErrorContract
{
    /** @var int */
    private static $nextId = 0;

    /** @var int */
    private $id;
    /** @var string */
    private $message;
    /** @var int */
    private $severity;
    /** @var string */
    private $file;
    /** @var int */
    private $line;
    /** @var string[] */
    private $trace;
    /** @var string|null */
    private $host;
    /** @var string|null */
    private $serverIp;
    /** @var string|null */
    private $remoteIp;
    /** @var string|null */
    private $sessionId;

    public function __construct()
    {
        $this->id = self::$nextId++;
        $this->host = self::arrayGet($_SERVER, 'HOSTNAME');
        $this->serverIp = self::arrayGet($_SERVER, 'SERVER_ADDR');
        $this->remoteIp = self::arrayGet($_SERVER, 'REMOTE_ADDR');
    }

    /**
     * @param string $message
     * @param int $severity
     * @param string $file
     * @param int $line
     * @param array $trace
     * @param string|null $basePath
     * @return Error
     */
    public static function create($message, $severity, $file, $line, array $trace, $basePath = null)
    {
        $instance = new static;
        $instance->message = $message;
        $instance->severity = $severity;
        $instance->file = $file;
        $instance->line = $line;
        $instance->trace = self::simplifiedBacktrace($trace, $basePath);

        return $instance;
    }

    /**
     * @param \ErrorException $exception
     * @return Error
     */
    public static function fromException(\ErrorException $exception)
    {
        return static::create(
            $exception->getMessage(),
            $exception->getSeverity(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTrace()
        );
    }

    /**
     * @param array|\ArrayAccess $data
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected static function arrayGet($data, $key, $default = null)
    {
        return (is_array($data) || $data instanceof \ArrayAccess) && isset($data[$key]) ? $data[$key] : $default;
    }

    /**
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data)
    {
        $instance = new static;
        $instance->message = self::arrayGet($data, 'message');
        $instance->severity = self::arrayGet($data, 'severity');
        $instance->file = self::arrayGet($data, 'file');
        $instance->line = self::arrayGet($data, 'line');
        $instance->trace = self::arrayGet($data, 'trace');
        $instance->host = self::arrayGet($data, 'host');
        $instance->serverIp = self::arrayGet($data, 'serverIp');
        $instance->remoteIp = self::arrayGet($data, 'remoteIp');

        return $instance;
    }

    /**
     * @param string $json
     * @return null|static
     */
    public static function fromJson($json)
    {
        $data = json_decode($json, true);

        return json_last_error()
            ? null
            : static::fromArray($data);
    }

    /**
     * @param array $backtrace
     * @param string|null $basePath
     * @return array
     */
    public static function simplifiedBacktrace(array $backtrace, $basePath = null)
    {
        static $parents = [];
        if (!isset($parents[static::class])) {
            $parents[static::class] = class_parents(static::class);
        }
        $trace = [];
        $basePathLen = strlen($basePath);
        foreach ($backtrace as $call) {
            $class = isset($call['class']) ? $call['class'] : null;
            if (static::class === $class || in_array($class, $parents[static::class])) {
                continue;
            }
            $file = isset($call['file']) ? $call['file'] : null;
            $file = $basePath && 0 === strpos($file, $basePath) ? substr($file, $basePathLen) : $file;
            $line = isset($call['line']) ? $call['line'] : null;;
            $type = isset($call['type']) ? $call['type'] : null;
            $function = isset($call['function']) ? $call['function'] : null;
            $trace[] = ($type ? "{$class}{$type}" : '') . ($function ? "{$function}()" : 'PHP') . ($file ? " @ {$file}({$line})" : '');
        }
        return $trace;
    }

    /**
     * @param string $id
     * @return static
     */
    public function withSessionId($id)
    {
        $this->sessionId = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return implode(
            ' ',
            [
                self::arrayGet(self::E_STRINGS, $this->severity, 'UNKNOWN'),
                "{$this->file}($this->line):",
                $this->message,
            ]
        );
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'session' => $this->sessionId,
            'message' => $this->message,
            'severity' => $this->severity,
            'file' => $this->file,
            'line' => $this->line,
            'trace' => $this->trace,
            'host' => $this->host,
            'serverIp' => $this->serverIp,
            'remoteIp' => $this->remoteIp,
        ];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return int
     */
    public function getSeverity()
    {
        return $this->severity;
    }

    /**
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @return int
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * @return string[]
     */
    public function getTrace()
    {
        return $this->trace;
    }

    /**
     * @return null|string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return null|string
     */
    public function getServerIp()
    {
        return $this->serverIp;
    }

    /**
     * @return null|string
     */
    public function getRemoteIp()
    {
        return $this->remoteIp;
    }

    /**
     * @return null|string
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
