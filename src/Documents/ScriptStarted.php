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
    private $path;
    /** @var string */
    private $hostname;
    /** @var string */
    private $serverIp;
    /** @var string */
    private $remoteIp;
    /** @var string */
    private $applicationName;
    /** @var string */
    private $method;

    /**
     * @return static
     */
    public static function fromGlobals()
    {
        $instance = new static;
        $instance->serverApi = strtoupper(PHP_SAPI);
        $instance->method = self::arrayGet($_SERVER, 'REQUEST_METHOD', $instance->serverApi);
        $instance->hostname = self::arrayGet($_SERVER, 'HTTP_HOST', 'localhost');
        $instance->script = self::arrayGet($_SERVER, 'SCRIPT_NAME');
        if (empty($_SERVER['HTTP_HOST'])) {
            $instance->script = realpath($instance->script);
        }
        $instance->scriptFilename = realpath($_SERVER['SCRIPT_FILENAME']);
        $instance->path = self::arrayGet($_SERVER, 'REQUEST_URI', $instance->script ?: $instance->scriptFilename);
        $instance->serverIp = self::arrayGet($_SERVER, 'SERVER_ADDR', '127.0.0.1');
        $instance->remoteIp = self::arrayGet($_SERVER, 'REMOTE_ADDR', '127.0.0.1');

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
        $this->path = self::arrayGet($data, 'path');
        $this->hostname = self::arrayGet($data, 'hostname');
        $this->serverIp = self::arrayGet($data, 'serverIp');
        $this->remoteIp = self::arrayGet($data, 'remoteIp');
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
            'path' => $this->path,
            'hostname' => $this->hostname,
            'serverIp' => $this->serverIp,
            'remoteIp' => $this->remoteIp,
            'applicationName' => $this->applicationName,
        ];
    }
}
