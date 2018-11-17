<?php

namespace Webgraphe\Phollow\Documents;

use Webgraphe\Phollow\Document;

class ScriptEnded extends Document
{
    /** @var string */
    const TYPE_SCRIPT_ENDED = 'scriptEnded';

    /** @var float */
    private $time;

    public static function fromGlobal()
    {
        $instance = new static;
        $instance->time = microtime(true);

        return $instance;
    }

    /**
     * @return string
     */
    public function getDocumentType()
    {
        return self::TYPE_SCRIPT_ENDED;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'time' => $this->time,
        ];
    }

    /**
     * @param array $data
     */
    protected function loadData(array $data)
    {
        $this->time = self::arrayGet($data, 'time');
    }
}
