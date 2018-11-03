<?php

namespace Webgraphe\Phollow;

class Configuration
{
    const SETTING_HELP = 'help';
    const SETTING_COLORS = 'colors';
    const SETTING_CONFIG = 'config';
    const SETTING_ERRORS_LOG_FILENAME = 'errors.log.filename';
    const SETTING_ORIGIN = 'origin';
    const SETTING_HTTP_PORT = 'http.port';
    const SETTING_WEBSOCKET_PORT = 'websocket.port';

    const DEFAULT_SETTINGS = [
        self::SETTING_HELP => false,
        self::SETTING_COLORS => false,
        self::SETTING_CONFIG => false,
        self::SETTING_ERRORS_LOG_FILENAME => '/dev/shm/phollow.errors.log',
        self::SETTING_ORIGIN => '',
        self::SETTING_HTTP_PORT => 8080,
        self::SETTING_WEBSOCKET_PORT => 8081,
    ];

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

        return $instance;
    }

    /**
     * @return Configuration
     * @throws \Exception
     */
    public static function fromGlobals()
    {
        $argv = array_slice($_SERVER['argv'], 1);
        $settings = [];
        foreach ($argv as $arg) {
            if (preg_match("/^--([^=]+)=?(.+)?$/", $arg, $matches)) {
                $settings[$matches[1]] = isset($matches[2]) ? $matches[2] : true;
            }
        }

        return static::fromArray($settings);
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
    public function showHelp()
    {
        return $this->getSetting(static::SETTING_HELP);
    }


    /**
     * @return bool
     */
    public function showConfig()
    {
        return $this->getSetting(static::SETTING_CONFIG);
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

    public function getUsage()
    {
        return <<<USAGE
Webgraphe Phollow

Usage:
    $ phollow [OPTIONS]

  Options:
    --help ···························· Displays usage
    --colors ·························· Toggles CLI colors
    --config ·························· Shows the configuration settings used
    --errors.log.filename=FILENAME ···· Specifies the log file to use
    --origin=HOST ····················· Specifies the host origin to match
    --http.port=PORT ·················· Specifies the HTTP port to use
    --websocket.port=PORT ············· Specifies the WebSocket port to use


USAGE;
    }

    public function getConfig()
    {
        foreach ($this->settings as $key => $value) {
            echo "$key=$value" . PHP_EOL;
        }

        echo PHP_EOL;
    }

    /**
     * @return string
     */
    public function getOrigin()
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
}
