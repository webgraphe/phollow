<?php

namespace Webgraphe\Phollow\Documents;

use Webgraphe\Phollow\Document;

class ConnectionClosed extends Document
{
    /** @var string */
    const TYPE_CONNECTION_CLOSED = 'connectionClosed';

    /**
     * @param int $scriptId
     * @return static
     */
    public static function fromScriptId($scriptId)
    {
        return (new static)->withScriptId($scriptId);
    }

    /**
     * @return string
     */
    public function getDocumentType()
    {
        return self::TYPE_CONNECTION_CLOSED;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [];
    }

    /**
     * @param array $data
     */
    protected function loadData(array $data)
    {
        // do nothing
    }
}
