<?php

namespace Webgraphe\Phollow\Documents;

use Webgraphe\Phollow\Contracts\ErrorCollectionContract;
use Webgraphe\Phollow\Contracts\ErrorContract;
use Webgraphe\Phollow\Document;

class ErrorCollection extends Document implements ErrorCollectionContract
{
    const TYPE_ERROR_COLLECTION ='errorCollection';

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
        $this->bySeverity[$error->getSeverityId()][$id] = $id;
        $this->byHost[$error->getHostName()][$id] = $id;
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
     * @param int $severity
     * @return int[]
     */
    public function getSeverity($severity)
    {
        return isset($this->bySeverity[$severity]) ? $this->bySeverity[$severity] : [];
    }

    /**
     * @param int $severity
     * @return int
     */
    public function getSeverityCount($severity)
    {
        return isset($this->bySeverity[$severity]) ? count($this->bySeverity[$severity]) : 0;
    }

    public function getSeverityCounts()
    {
        $counts = array_map(
            function ($severity) {
                return $this->getSeverityCount($severity);
            },
            array_flip(ErrorContract::E_STRINGS)
        );

        arsort($counts);

        return $counts;
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
     * @return int A whole number
     */
    public function count()
    {
        return count($this->errors);
    }

    /**
     * @return string
     */
    public function getDocumentType()
    {
        return self::TYPE_ERROR_COLLECTION;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->errors;
    }
}
