<?php

namespace Webgraphe\Phollow\Components;

use Evenement\EventEmitter;
use React\Stream\WritableStreamInterface;
use Webgraphe\Phollow\Contracts\ErrorCollectionContract;
use Webgraphe\Phollow\Contracts\ErrorContract;
use Webgraphe\Phollow\Documents;
use Webgraphe\Phollow\Tracer;

class WritableErrorStream extends EventEmitter implements WritableStreamInterface, ErrorCollectionContract
{
    const EVENT_ERROR_ADDED = 'errorAdded';

    /** @var ErrorContract[] */
    private $errors = [];
    private $bySeverity = [];
    private $byHost = [];
    private $byLocation = [];
    private $bySessionId = [];
    /** @var Tracer */
    private $tracer;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
    }

    /**
     * @return bool
     */
    public function isWritable()
    {
        return true;
    }

    /**
     * @param mixed|string $data
     * @return bool
     */
    public function write($data)
    {
        try {
            $this->addError(Documents\Error::fromJson($data));
        } catch (\Exception $e) {
            $this->tracer->error($e->getMessage());
        }

        return true;
    }

    public function end($data = null)
    {
        // do nothing
    }

    public function close()
    {
        // do nothing
    }

    private function addError(ErrorContract $error)
    {
        $this->tracer->info($error);

        $id = $error->getId();
        $this->errors[$id] = $error;
        $this->bySeverity[$error->getSeverity()][$id] = $id;
        $this->byHost[$error->getHost()][$id] = $id;
        $this->byLocation[$error->getFile() . ':' . $error->getLine()][$id] = $id;
        $this->bySessionId[$error->getSessionId()][$id] = $id;

        $this->emit(self::EVENT_ERROR_ADDED, [$error]);
    }

    /**
     * @param array $array
     * @return array
     */
    private function getSortedKeys(array $array)
    {
        ksort($keys = array_keys($array));

        return $keys;
    }

    /**
     * @return string[]
     */
    public function getSeverities()
    {
        $keys = $this->getSortedKeys($this->bySeverity);

        return array_combine(
            $keys,
            array_map(
                function ($key) {
                    return ErrorContract::E_STRINGS[$key];
                },
                $keys
            )
        );
    }

    /**
     * @return array
     */
    public function getHosts()
    {
        return $this->getSortedKeys($this->byHost);
    }

    /**
     * @return string[]
     */
    public function getLocations()
    {
        return $this->getSortedKeys($this->byLocation);
    }

    /**
     * @return string[]
     */
    public function getSessionIds()
    {
        return $this->getSortedKeys($this->bySessionId);
    }

    /**
     * @param callable $callback
     * @return static
     */
    public function onErrorAdded(callable $callback)
    {
        return $this->on(self::EVENT_ERROR_ADDED, $callback);
    }

    /**
     * @return ErrorContract[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param int $id
     * @return ErrorContract|null
     */
    public function getError($id)
    {
        return isset($this->errors[$id]) ? $this->errors[$id] : null;
    }

    /**
     * @return ErrorContract[]
     */
    public function jsonSerialize()
    {
        return $this->errors;
    }
}
