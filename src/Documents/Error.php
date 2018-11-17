<?php

namespace Webgraphe\Phollow\Documents;

use Webgraphe\Phollow\Document;

class Error extends Document
{
    /** @var string */
    const TYPE_ERROR = 'error';

    /** @var int */
    const E_UNKNOWN = 0;

    /** @var string[] */
    const E_STRINGS = [
        self::E_UNKNOWN => 'UNKNOWN',
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE_ERROR',
        E_CORE_WARNING => 'CORE_WARNING',
        E_COMPILE_ERROR => 'COMPILE_ERROR',
        E_COMPILE_WARNING => 'COMPILE_WARNING',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER_DEPRECATED',
    ];

    /** @var int|null */
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
    /** @var string \DateTime::ATOM format preferred */
    private $timestamp;

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
     * @param array $data
     */
    protected function loadData(array $data)
    {
        $this->timestamp = self::arrayGet($data, 'timestamp');
        $this->message = self::arrayGet($data, 'message');
        $this->severityId = self::arrayGet($data, 'severityId');
        $this->file = self::arrayGet($data, 'file');
        $this->line = self::arrayGet($data, 'line');
        $this->trace = self::arrayGet($data, 'trace');
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
            'message' => $this->message,
            'severityId' => $this->severityId,
            'severityName' => $this->getSeverityName(),
            'file' => $this->file,
            'line' => $this->line,
            'trace' => $this->trace,
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
     * Mostly used by the ErrorCollection in the Application.
     *
     * @return int|null
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