<?php

namespace Webgraphe\Phollow\Components;

use Evenement\EventEmitter;
use React\Stream\WritableStreamInterface;
use Webgraphe\Phollow\Documents;
use Webgraphe\Phollow\Tracer;

class WritableErrorStream extends EventEmitter implements WritableStreamInterface
{
    /** @var Documents\Error */
    private $errors = [];
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
            $this->addError(Documents\Error::fromJson($data));
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

    private function addError(Documents\Error $error)
    {
        $this->tracer->info($error);

        $this->errors[] = $error;
    }
}
