<?php

namespace Webgraphe\Phollow;

abstract class Document implements \JsonSerializable
{
    /** @var int */
    private $id;
    /** @var string|null */
    private $scriptId;

    final protected function __construct()
    {
        // do nothing
    }

    /**
     * @return string
     */
    abstract public function getDocumentType();

    /**
     * @return array
     */
    abstract public function toArray();

    /**
     * @param array $data
     */
    abstract protected function loadData(array $data);

    /**
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data)
    {
        $instance = new static;

        $meta = (array)self::arrayGet($data, 'meta');
        $instance->scriptId = self::arrayGet($meta, 'scriptId');
        $instance->loadData((array)self::arrayGet($data, 'data'));

        return $instance;
    }
    /**
     * @param array|\ArrayAccess $data
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected static function arrayGet($data, $key, $default = null)
    {
        return (is_array($data) || $data instanceof \ArrayAccess) && isset($data[$key]) ? $data[$key] : $default;
    }

    /**
     * @return array
     */
    final public function jsonSerialize()
    {
        return [
            'meta' => [
                'id' => $this->id,
                'type' => $this->getDocumentType(),
                'scriptId' => $this->scriptId,
            ],
            'data' => $this->toArray()
        ];
    }

    /**
     * @param int $id
     * @return $this
     */
    public function withId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function withScriptId($id)
    {
        $this->scriptId = $id;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getScriptId()
    {
        return $this->scriptId;
    }

}
