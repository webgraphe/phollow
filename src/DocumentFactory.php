<?php

namespace Webgraphe\Phollow;

class DocumentFactory
{
    /**
     * @var string[] MUST implement Document
     * @see Document
     */
    const TYPES = [
        Documents\ScriptStarted::TYPE_SCRIPT_STARTED => Documents\ScriptStarted::class,
        Documents\Error::TYPE_ERROR => Documents\Error::class,
        Documents\ScriptEnded::TYPE_SCRIPT_ENDED => Documents\ScriptEnded::class,
    ];

    /**
     * @param array $data
     * @return Document|null
     */
    public static function fromArray(array $data)
    {
        $type = isset($data['meta']['type']) ? $data['meta']['type'] : null;

        if (array_key_exists($type, self::TYPES)) {
            $class = self::TYPES[$type];

            return call_user_func([$class, 'fromArray'], $data);
        }

        return null;
    }
}
