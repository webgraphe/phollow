<?php

namespace Webgraphe\Phollow;

class Tracer
{
    /** @var string White */
    const CLI_COLOR_SETUP = '0;36';
    /** @var string Red */
    const CLI_COLOR_ERROR = '0;31';
    /** @var string Yellow */
    const CLI_COLOR_WARNING = '0;33';
    /** @var string Green */
    const CLI_COLOR_NOTICE = '0;32';

    /** @var string|null */
    private $component;
    /** @var Configuration */
    private $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @param string $message
     * @param string|null $color
     */
    private function message($message, $color = null)
    {
        $useColors = $this->configuration->useColors();

        if ($this->component) {
            $now = (new \DateTime)->format(\DateTime::ATOM);
            echo $useColors
                ? "\033[1;37m{$this->component}\033[0m \033[1;30m{$now}\033[0m "
                : "{$this->component} $now ";
        }

        echo ($useColors && $color ? "\033[{$color}m{$message}\033[0m" : $message) . PHP_EOL;
    }

    /**
     * @param string $message
     */
    public function setup($message)
    {
        $this->message($message, static::CLI_COLOR_SETUP);
    }

    /**
     * @param string $message
     */
    public function info($message)
    {
        $this->message($message);
    }

    /**
     * @param string $message
     */
    public function notice($message)
    {
        $this->message($message, static::CLI_COLOR_NOTICE);
    }

    /**
     * @param string $message
     */
    public function warning($message)
    {
        $this->message($message, static::CLI_COLOR_WARNING);
    }

    /**
     * @param string $message
     */
    public function error($message)
    {
        $this->message($message, static::CLI_COLOR_ERROR);
    }

    /**
     * @param string $component
     * @return static
     */
    public function withComponent($component)
    {
        $clone = clone $this;
        $clone->component = $component;

        return $clone;
    }
}
