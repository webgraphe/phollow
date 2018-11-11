<?php

namespace Webgraphe\Phollow\Components;

use Evenement\EventEmitter;
use React\Stream\WritableStreamInterface;
use Webgraphe\Phollow\Documents;
use Webgraphe\Phollow\Tracer;

class WritableLogStream extends EventEmitter implements WritableStreamInterface
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
        foreach (explode(PHP_EOL, trim($data)) as $line) {
            $decoded = json_decode($line, true);
            try {
                if (!$decoded) {
                    throw new \Exception("Failed decoding data(" . json_last_error_msg() ."); " . $line);
                }

                // TODO Implement document factory
                $type = isset($decoded['meta']['type']) ? $decoded['meta']['type'] : null;
                $documentData = isset($decoded['data']) ? $decoded['data'] : null;
                switch ($type) {
                    case 'error':
                        if ($error = Documents\Error::fromArray((array)$documentData)) {
                            $this->emit(self::EVENT_NEW_ERROR, [$error]);
                        } else {
                            throw new \Exception("Failed creating error document");
                        }

                        break;
                    default:
                        throw new \Exception("Unhandled document type '$type'");
                }
            } catch (\Exception $e) {
                $this->tracer->error($e->getMessage());
            }
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
