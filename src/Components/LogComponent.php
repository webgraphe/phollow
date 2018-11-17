<?php

namespace Webgraphe\Phollow\Components;

use Evenement\EventEmitter;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Webgraphe\Phollow\Contracts\OnNewDocumentContract;
use Webgraphe\Phollow\DocumentFactory;
use Webgraphe\Phollow\Documents\ConnectionClosed;
use Webgraphe\Phollow\Documents\ConnectionOpened;
use Webgraphe\Phollow\Tracer;

class LogComponent extends EventEmitter implements MessageComponentInterface
{
    /** @var string */
    const EVENT_NEW_DOCUMENT = 'newDocument';

    /** @var Tracer */
    private $tracer;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
    }

    /**
     * @param ConnectionInterface $conn
     * @return int
     */
    public static function connectionResourceId(ConnectionInterface $conn)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        return (int)$conn->resourceId;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $resourceId = self::connectionResourceId($conn);
        $this->tracer->notice("#{$resourceId} Connected");
        $this->emit(
            self::EVENT_NEW_DOCUMENT,
            [ConnectionOpened::fromScriptId($resourceId)]
        );
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        foreach (explode(PHP_EOL, trim($msg)) as $line) {
            $decoded = json_decode($line, true);
            try {
                if (json_last_error()) {
                    throw new \Exception("Failed decoding data(" . json_last_error_msg() ."); " . $line);
                }

                if (!is_array($decoded)) {
                    throw new \Exception("Illogical document decoded" . $line);
                }

                if ($document = DocumentFactory::fromArray($decoded)) {
                    $this->emit(
                        self::EVENT_NEW_DOCUMENT,
                        [$document->withScriptId(self::connectionResourceId($from))]
                    );
                } else {
                    throw new \Exception("Unhandled document message");
                }
            } catch (\Exception $e) {
                $this->tracer->error($e->getMessage());
            }
        }

        return true;
    }

    public function onClose(ConnectionInterface $conn)
    {
        $resourceId = self::connectionResourceId($conn);
        $this->tracer->notice("#{$resourceId} Disconnected");
        $this->emit(
            self::EVENT_NEW_DOCUMENT,
            [ConnectionClosed::fromScriptId($resourceId)]
        );
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->tracer->error($e->getMessage());

        $conn->close();
    }

    /**
     * @param OnNewDocumentContract|callable $callback
     * @return static
     */
    public function onNewDocument(callable $callback)
    {
        try {
            return $this->on(self::EVENT_NEW_DOCUMENT, $callback);
        } catch (\Exception $e) {
            $this->tracer->error($e->getMessage());
        }

        return $this;
    }
}
