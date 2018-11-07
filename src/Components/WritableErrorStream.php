<?php

namespace Webgraphe\Phollow\Components;

use Evenement\EventEmitter;
use React\Stream\WritableStreamInterface;
use Webgraphe\Phollow\Documents;
use Webgraphe\Phollow\Tracer;

class WritableErrorStream extends EventEmitter implements WritableStreamInterface
{
    const EVENT_NEW_ERROR = 'newError';

    /** @var Tracer */
    private $tracer;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
    }

    /**
     * @return bool
     */
    public function isWritable()
    {
        return true;
    }

    /**
     * @param mixed|string $data
     * @return bool
     */
    public function write($data)
    {
        try {
            $this->emit(self::EVENT_NEW_ERROR, [Documents\Error::fromJson($data)]);
        } catch (\Exception $e) {
            $this->tracer->error($e->getMessage());
        }

        return true;
    }

    public function end($data = null)
    {
        // do nothing
    }

    public function close()
    {
        // do nothing
    }

    /**
     * @param callable $callback
     * @return static
     */
    public function onNewError(callable $callback)
    {
        return $this->on(self::EVENT_NEW_ERROR, $callback);
    }
}
