<?php

namespace Webgraphe\Phollow\Documents;

use Webgraphe\Phollow\Document;

class ScriptEnded extends Document
{
    /** @var string */
    const TYPE_SCRIPT_ENDED = 'scriptEnded';

    /** @var float */
    private $time;

    public static function fromGlobal($startTime = null)
    {
        $instance = new static;
        $ru = getrusage();
        $instance->time = ($ru['ru_utime.tv_usec'] / 1000000) + $ru['ru_utime.tv_sec'];
        if (null !== $startTime) {
            $instance->time += microtime(true) - $startTime;
        }

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
