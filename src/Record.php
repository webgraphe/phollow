<?php

namespace Webgraphe\Phollow;

class Record implements \JsonSerializable
{
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

    public function __construct($message, $severity, $file, $line, array $trace)
    {
        $this->message = $message;
        $this->severity = $severity;
        $this->file = $file;
        $this->line = $line;
        $this->trace = $trace;
    }

    public static function fromException(\ErrorException $exception)
    {
        return new static(
            $exception->getMessage(),
            $exception->getSeverity(),
            $exception->getFile(),
            $exception->getLine(),
            explode(PHP_EOL, $exception->getTraceAsString())
        );
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        // TODO: Implement jsonSerialize() method.
    }
}
