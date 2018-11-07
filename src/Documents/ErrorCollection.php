<?php

namespace Webgraphe\Phollow\Documents;

use Webgraphe\Phollow\Contracts\ErrorCollectionContract;
use Webgraphe\Phollow\Contracts\ErrorContract;

class ErrorCollection implements ErrorCollectionContract
{
    /** @var ErrorContract[] */
    private $errors = [];
    private $bySeverity = [];
    private $byHost = [];
    private $byLocation = [];
    private $bySessionId = [];

    public function addError(ErrorContract $error)
    {
        $id = $error->getId();
        $this->errors[$id] = $error;
        $this->bySeverity[$error->getSeverity()][$id] = $id;
        $this->byHost[$error->getHost()][$id] = $id;
        $this->byLocation[$error->getFile() . ':' . $error->getLine()][$id] = $id;
        $this->bySessionId[$error->getSessionId()][$id] = $id;
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
     * @return mixed|ErrorContract[]
     */
    public function jsonSerialize()
    {
        return $this->getErrors();
    }
}
