<?php

namespace Webgraphe\Phollow\Documents;

use Webgraphe\Phollow\Document;

class DocumentCollection extends Document implements \Countable
{
    /** @var string */
    const TYPE_DOCUMENT_COLLECTION = 'documentCollection';

    /** @var int */
    private static $nextId = 0;

    /** @var Document[] */
    private $documents = [];
    /** @var int[][] */
    private $scripts = [];
    /** @var int[] */
    private $terminatedScripts = [];

    /**
     * @return static
     */
    public static function create()
    {
        return new static;
    }

    /**
     * @param Document $document
     * @return int
     */
    protected function addDocument(Document $document)
    {
        $document->withId($id = self::$nextId++);
        $this->documents[$id] = $document;
        $this->scripts[$document->getScriptId()][$id] = $id;

        return $id;
    }

    /**
     * @param ConnectionOpened $connectionOpened
     * @return int
     * @throws \Exception
     */
    public function openConnection(ConnectionOpened $connectionOpened)
    {
        $scriptId = $connectionOpened->getScriptId();
        if (isset($this->scripts[$scriptId])) {
            throw new \Exception("Illogical document conversation; script ID {$scriptId} already exists");
        }

        return  $this->addDocument($connectionOpened);
    }

    /**
     * @param ScriptStarted $scriptStarted
     * @return int
     * @throws \Exception
     */
    public function startScript(ScriptStarted $scriptStarted)
    {
        $this->guardAgainstInvalidDocument($scriptStarted);

        return $this->addDocument($scriptStarted);
    }

    /**
     * @param Error $error
     * @return int
     * @throws \Exception
     */
    public function addError(Error $error)
    {
        $this->guardAgainstInvalidDocument($error);

        return $this->addDocument($error);
    }

    /**
     * @param ScriptEnded $scriptEnded
     * @return int
     * @throws \Exception
     */
    public function endScript(ScriptEnded $scriptEnded)
    {
        $this->guardAgainstInvalidDocument($scriptEnded);

        return $this->addDocument($scriptEnded);
    }

    /**
     * @param ConnectionClosed $connectionClosed
     * @return int
     * @throws \Exception
     */
    public function closeConnection(ConnectionClosed $connectionClosed)
    {
        $this->guardAgainstInvalidDocument($connectionClosed);

        $id = $this->addDocument($connectionClosed);
        $scriptId = $connectionClosed->getScriptId();
        $this->terminatedScripts[$scriptId] = $id;

        return $id;
    }

    /**
     * @return int A whole number
     */
    public function count()
    {
        return count($this->documents);
    }

    /**
     * @return string
     */
    public function getDocumentType()
    {
        return self::TYPE_DOCUMENT_COLLECTION;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->documents;
    }

    /**
     * @return Document[]
     */
    public function getDocuments()
    {
        return $this->documents;
    }

    /**
     * @param int $id
     * @return Document|null
     */
    public function getDocument($id)
    {
        return isset($this->documents[$id]) ? $this->documents[$id] : null;
    }

    /**
     * @param array $data
     * @throws \Exception
     */
    protected function loadData(array $data)
    {
        throw new \Exception("Not implemented");
    }

    /**
     * @param Document $document
     * @throws \Exception
     */
    private function guardAgainstInvalidDocument(Document $document)
    {
        $scriptId = $document->getScriptId();
        if (!isset($this->scripts[$scriptId])) {
            throw new \Exception("Illogical document conversation; undefined script ID {$scriptId}");
        }
        if (isset($this->terminatedScripts[$scriptId])) {
            throw new \Exception("Illogical document conversation; script ID {$scriptId} terminated");
        };
    }
}
