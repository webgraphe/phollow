<?php

namespace Webgraphe\Phollow\Documents;

use Webgraphe\Phollow\Document;

class ScriptStarted extends Document
{
    /** @var string */
    const TYPE_SCRIPT_STARTED = 'scriptStarted';

    /** @var string */
    private $serverApi;
    /** @var string */
    private $script;
    /** @var string */
    private $scriptFilename;
    /** @var string */
    private $hostname;
    /** @var string */
    private $serverIp;
    /** @var string */
    private $remoteIp;
    /** @var float */
    private $time;
    /** @var string */
    private $applicationName;
    /** @var string */
    private $method;

    /**
     * @return static
     */
    public static function fromGlobals()
    {
        $ru = getrusage();
        $time = microtime(true) - $ru['ru_utime.tv_usec'] - $ru['ru_utime.tv_sec'];

        $instance = new static;
        $instance->serverApi = PHP_SAPI;
        $instance->method = self::arrayGet($_SERVER, 'REQUEST_METHOD');
        $instance->hostname = self::arrayGet($_SERVER, 'HOSTNAME');
        $instance->script = self::arrayGet($_SERVER, 'SCRIPT');
        $instance->scriptFilename = realpath($_SERVER['SCRIPT_FILENAME']);
        $instance->serverIp = self::arrayGet($_SERVER, 'SERVER_ADDR');
        $instance->remoteIp = self::arrayGet($_SERVER, 'REMOTE_ADDR');
        $instance->time = $time;

        return $instance;
    }

    /**
     * @param array $data
     */
    protected function loadData(array $data)
    {
        $this->serverApi = self::arrayGet($data, 'serverApi');
        $this->method = self::arrayGet($data, 'method');
        $this->script = self::arrayGet($data, 'script');
        $this->scriptFilename = self::arrayGet($data, 'scriptFilename');
        $this->hostname = self::arrayGet($data, 'hostname');
        $this->serverIp = self::arrayGet($data, 'serverIp');
        $this->remoteIp = self::arrayGet($data, 'remoteIp');
        $this->time = self::arrayGet($data, 'time');
        $this->applicationName = self::arrayGet($data, 'applicationName');
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
     * @return string
     */
    public function getDocumentType()
    {
        return self::TYPE_SCRIPT_STARTED;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'method' => $this->method,
            'serverApi' => $this->serverApi,
            'script' => $this->script,
            'scriptFilename' => $this->scriptFilename,
            'hostname' => $this->hostname,
            'serverIp' => $this->serverIp,
            'remoteIp' => $this->remoteIp,
            'time' => $this->time,
            'applicationName' => $this->applicationName,
        ];
    }
}
