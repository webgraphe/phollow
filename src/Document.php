<?php

namespace Webgraphe\Phollow;

abstract class Document implements \JsonSerializable
{
    /**
     * @return string
     */
    abstract public function getDocumentType();

    /**
     * @return array
     */
    abstract public function toArray();

    final public function jsonSerialize()
    {
        return [
            'meta' => [
                'type' => $this->getDocumentType()
            ],
            'data' => $this->toArray()
        ];
    }
}
