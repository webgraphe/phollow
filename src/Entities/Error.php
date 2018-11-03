<?php

namespace Webgraphe\Phollow\Entities;

class Error
{
    /**
     * @param array $data
     * @return static
     */
    public final static function fromArray(array $data)
    {
        $instance = new static;
        // TODO Parse $data

        return $instance;
    }

    /**
     * @param string $json
     * @return null|static
     */
    public static function fromJson($json)
    {
        $data = json_decode($json, true);

        return json_last_error()
            ? null
            : static::fromArray($data);
    }

    public function __toString()
    {
        // TODO Not implemented
        return 'Not implemented';
    }
}
