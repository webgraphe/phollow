<?php

namespace Webgraphe\Phollow\Documents;

use Webgraphe\Phollow\Contracts\ErrorContract;
use Webgraphe\Phollow\Document;

class Error extends Document implements ErrorContract
{
    const TYPE_ERROR = 'error';

    /** @var int */
    private static $nextId = 0;

    /** @var int TODO Does not belong here */
    private $id;
    /** @var string */
    private $message;
    /** @var int */
    private $severityId;
    /** @var string */
    private $file;
    /** @var int */
    private $line;
    /** @var string[] */
    private $trace;
    /** @var string|null */
    private $hostName;
    /** @var string|null */
    private $serverIp;
    /** @var string|null */
    private $remoteIp;
    /** @var string|null */
    private $sessionId;
    /** @var string \DateTime::ATOM format preferred */
    private $timestamp;
    /** @var string */
    private $applicationName;

    public function __construct()
    {
        $this->id = self::$nextId++;
        $this->hostName = self::arrayGet($_SERVER, 'HOSTNAME');
        $this->serverIp = self::arrayGet($_SERVER, 'SERVER_ADDR');
        $this->remoteIp = self::arrayGet($_SERVER, 'REMOTE_ADDR');
    }

    /**
     * @param string $message
     * @param int $severityId
     * @param string $file
     * @param int $line
     * @param array $trace
     * @param string|null $basePath
     * @return Error
     */
    public static function create($message, $severityId, $file, $line, array $trace, $basePath = null)
    {
        $instance = new static;
        $instance->timestamp = (new \DateTime)->format(\DateTime::ATOM);
        $instance->message = $message;
        $instance->severityId = $severityId;
        $basePathLength = strlen($basePath);
        $instance->file = $basePathLength && 0 === strpos($file, $basePath) ? substr($file, $basePathLength + 1) : $file;
        $instance->line = $line;
        $instance->trace = self::simplifiedBacktrace($trace, $basePath);

        return $instance;
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
     * @return static|null
     */
    public static function fromArray(array $data)
    {
        if (!$data) {
            return null;
        }

        $instance = new static;
        $instance->timestamp = self::arrayGet($data, 'timestamp');
        $instance->message = self::arrayGet($data, 'message');
        $instance->sessionId = self::arrayGet($data, 'sessionId');
        $instance->severityId = self::arrayGet($data, 'severityId');
        $instance->file = self::arrayGet($data, 'file');
        $instance->line = self::arrayGet($data, 'line');
        $instance->trace = self::arrayGet($data, 'trace');
        $instance->hostName = self::arrayGet($data, 'hostName');
        $instance->applicationName = self::arrayGet($data, 'applicationName');
        $instance->serverIp = self::arrayGet($data, 'serverIp');
        $instance->remoteIp = self::arrayGet($data, 'remoteIp');

        return $instance;
    }

    /**
     * @return string
     */
    public function getDocumentType()
    {
        return self::TYPE_ERROR;
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
        $basePathLength = strlen($basePath);
        foreach ($backtrace as $call) {
            $class = isset($call['class']) ? $call['class'] : null;
            if (static::class === $class || in_array($class, $parents[static::class])) {
                continue;
            }
            $file = isset($call['file']) ? $call['file'] : null;
            $file = $basePathLength && 0 === strpos($file, $basePath) ? substr($file, $basePathLength + 1) : $file;
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
     * @param string $name
     * @return static
     */
    public function withApplicationName($name)
    {
        $this->applicationName = $name;

        return $this;
    }

    /**
     * @param string $name
     * @return static
     */
    public function withHostName($name)
    {
        $this->hostName = $name;

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
                self::arrayGet(self::E_STRINGS, $this->severityId, 'UNKNOWN'),
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
            'timestamp' => $this->timestamp,
            'sessionId' => $this->sessionId,
            'message' => $this->message,
            'severityId' => $this->severityId,
            'severityName' => $this->getSeverityName(),
            'file' => $this->file,
            'line' => $this->line,
            'trace' => $this->trace,
            'hostName' => $this->hostName,
            'applicationName' => $this->applicationName,
            'serverIp' => $this->serverIp,
            'remoteIp' => $this->remoteIp,
        ];
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
    public function getSeverityId()
    {
        return $this->severityId;
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
    public function getHostName()
    {
        return $this->hostName;
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

    /**
     * @return string
     */
    public function getSeverityName()
    {
        return array_key_exists($this->severityId, self::E_STRINGS) ? self::E_STRINGS[$this->severityId] : self::E_STRINGS[self::E_UNKNOWN];
    }
}