<?php

namespace Webgraphe\Phollow\Components;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Webgraphe\Phollow\Document;
use Webgraphe\Phollow\Tracer;

class NotificationComponent implements MessageComponentInterface
{
    /** @var \SplObjectStorage|ConnectionInterface[] */
    protected $clients;
    /** @var Tracer */
    private $tracer;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
        $this->clients = new \SplObjectStorage;
    }

    /**
     * @param ConnectionInterface $conn
     * @return false|string
     */
    public static function connectionRemoteAddress(ConnectionInterface $conn)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        return $conn->remoteAddress;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $this->tracer->notice(self::connectionRemoteAddress($conn) . ' Connected');
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $this->tracer->info(self::connectionRemoteAddress($from) . " (ignored) $msg");
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $this->tracer->notice(self::connectionRemoteAddress($conn) . ' Disconnected');
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->tracer->error($e->getMessage());

        $conn->close();
    }

    public function notifyNewDocument(Document $document)
    {
        foreach ($this->clients as $client) {
            $client->send(json_encode($document));
        }
    }
}
