<?php

namespace Webgraphe\Phollow;

class ParameterBag
{
    /** @var string[] */
    private $arguments = array();
    /** @var string[] */
    private $options = array();

    /**
     * @param string[] $arguments
     */
    protected function __construct(array $arguments)
    {
        foreach ($arguments as $argument) {
            if (preg_match("/--([^=]+)=?(.+)?/", $argument, $matches)) {
                $this->options[$matches[1]] = array_key_exists(2, $matches) ? $matches[2] : true;
            } else {
                $this->arguments[] = $argument;
            }
        }
    }

    /**
     * @param string[] $parameters
     * @return static
     */
    public static function fromArray(array $parameters)
    {
        return new static($parameters);
    }

    /**
     * @return static
     */
    public static function fromGlobals()
    {
        return new static(array_slice($_SERVER['argv'], 1));
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getOption($name, $default = null)
    {
        return $this->hasOption($name) ? $this->options[$name] : $default;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasOption($name)
    {
        return array_key_exists($name, $this->options);
    }

    /**
     * @param callable|null $filter
     * @param int $arrayFilterFlag
     * @return array|string[]
     */
    public function getOptions(callable $filter = null, $arrayFilterFlag = 0)
    {
        return $filter ? array_filter($this->options, $filter, $arrayFilterFlag) : $this->options;
    }

    /**
     * @return string[]
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param int $index
     * @param mixed $default
     * @return string
     */
    public function getArgument($index, $default = null)
    {
        return array_key_exists($index, $this->arguments) ? $this->arguments[$index] : $default;
    }
}
