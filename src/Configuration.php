<?php

namespace Webgraphe\Phollow;

class Configuration
{
    const SETTING_COLORS = 'colors';
    const SETTING_ERRORS_LOG_FILENAME = 'errors.log.filename';
    const SETTING_ORIGIN = 'origin';
    const SETTING_HTTP_PORT = 'http.port';
    const SETTING_WEBSOCKET_PORT = 'websocket.port';

    const DEFAULT_SETTINGS = [
        self::SETTING_COLORS => false,
        self::SETTING_ERRORS_LOG_FILENAME => '/dev/shm/phollow.errors.log',
        self::SETTING_ORIGIN => '',
        self::SETTING_HTTP_PORT => 8080,
        self::SETTING_WEBSOCKET_PORT => 8081,
    ];

    const SETTING_DESCRIPTIONS = [
        self::SETTING_COLORS => 'Toggles use of CLI colors',
        self::SETTING_ERRORS_LOG_FILENAME => 'Dumps error logs in file (default=/dev/shm/phollow.errors.log)',
        self::SETTING_ORIGIN => 'Specifics HTTP and WebSocket origin to match',
        self::SETTING_HTTP_PORT => 'Port of the HTTP server (default=8080)',
        self::SETTING_WEBSOCKET_PORT => 'Port of the WebSocket server (default=8081)',
    ];

    /** @var string */
    private $summary = 'Using default settings';
    /** @var Tracer */
    private $tracer;
    /** @var array */
    private $settings = self::DEFAULT_SETTINGS;

    public function __construct()
    {
        $this->tracer = new Tracer($this);
    }

    /**
     * @param array $settings
     * @return Configuration
     * @throws \Exception
     */
    public static function fromArray(array $settings)
    {
        $instance = new static;
        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, $instance->settings)) {
                throw new \Exception("Unknown configuration setting '$key'");
            }
            if (is_numeric($value)) {
                $value = $value + 0;
            }
            $expectedType = gettype($instance->settings[$key]);
            $actualType = gettype($value);
            if ($actualType !== $expectedType) {
                throw new \Exception("Expected configuration setting '$key' to be of type '$expectedType'; got '$actualType'");
            }
            $instance->settings[$key] = $value;
        }

        return $instance->withSummary('Using default settings' . ($settings ? ' with overrides' : ''));
    }

    /**
     * @param string $file
     * @param array $overrides
     * @return Configuration
     * @throws \Exception
     */
    public static function fromIniFile($file, array $overrides = [])
    {
        if (!file_exists($file)) {
            throw new \Exception("Configuration file '$file' not found");
        }

        $ini = @file_get_contents($file);
        if (false === $ini) {
            throw new \Exception("'$file' is not an INI file");
        }

        if (is_array($data = parse_ini_string($ini, false, INI_SCANNER_TYPED))) {
            return static::fromArray(array_merge($data, $overrides))
                ->withSummary("Using settings from '$file'" . ($overrides ? ' with overrides' : ''));
        }

        throw new \Exception("Failed parsing INI file '$file'");
    }

    /**
     * @return string
     */
    public function getErrorLogFile()
    {
        return $this->getSetting(static::SETTING_ERRORS_LOG_FILENAME);
    }

    /**
     * @return bool
     */
    public function useColors()
    {
        return $this->getSetting(static::SETTING_COLORS);
    }

    /**
     * @return Tracer
     */
    public function getTracer()
    {
        return $this->tracer;
    }

    /**
     * @param string $name
     * @return mixed
     */
    private function getSetting($name)
    {
        return $this->settings[$name];
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $data = $this->settings;

        return $data;
    }

    /**
     * @return string
     */
    public function toIni()
    {
        $ini = [];
        foreach ($this->toArray() as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (null === $value) {
                $value = 'null';
            }
            $ini[] = "$key=$value";
        }

        return implode(PHP_EOL, $ini);
    }

    /**
     * @return string
     */
    public function getServerOrigin()
    {
        return $this->getSetting(static::SETTING_ORIGIN);
    }

    /**
     * @return string
     */
    public function getWebSocketPort()
    {
        return $this->getSetting(static::SETTING_WEBSOCKET_PORT);
    }

    /**
     * @return string
     */
    public function getHttpPort()
    {
        return $this->getSetting(static::SETTING_HTTP_PORT);
    }

    /**
     * @param string $summary
     * @return $this
     */
    private function withSummary($summary)
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * @return string
     */
    public function getSummary()
    {
        return $this->summary;
    }
}
