<?php

namespace Webgraphe\Phollow;

class ErrorHandler
{
    /** @var int|null */
    private $errorReporting;
    /** @var array */
    private $excludes = [];
    /** @var array */
    private $includes = [];

    /**
     * @param $level
     * @param $message
     * @param $file
     * @param $line
     * @return bool
     */
    public function __invoke($level, $message, $file, $line)
    {
        if (!$this->handlesLevel($level) || !$this->handlesFile($file)) {
            return false;
        }

        $json = json_encode(Record::fromException(new \ErrorException($message, 0, $level, $file, $line)));

        // TODO POST without waiting for response
    }

    /**
     * Overrides global error reporting for this handler if not null.
     *
     * @param int|null $mask
     */
    public function setErrorReporting($mask)
    {
        $this->errorReporting = null === $mask ? null : (int)$mask;
    }

    /**
     * @param string $pattern
     * @param int $flags One of fnmatch() supported flags
     * @see fnmatch()
     */
    public function excludes($pattern, $flags = 0)
    {
        $this->excludes[$pattern] = [$pattern, $flags];
    }

    /**
     * @param string $pattern
     * @param int $flags One of fnmatch() supported flags
     * @see fnmatch()
     */
    public function includes($pattern, $flags = 0)
    {
        $this->includes[$pattern] = [$pattern, $flags];
    }

    /**
     * @param string $file
     * @return bool
     */
    public function handlesFile($file)
    {
        foreach ($this->excludes as $pattern) {
            if (fnmatch($pattern[0], $file, $pattern[1])) {
                return false;
            }
        }

        if ($this->includes) {
            foreach ($this->includes as $pattern) {
                if (fnmatch($pattern[0], $file, $pattern[1])) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * @param int
     * @return bool
     */
    public function handlesLevel($level)
    {
        $errorReporting = null !== $this->errorReporting ? $this->errorReporting : error_reporting();

        return (bool)($errorReporting & $level);
    }
}
