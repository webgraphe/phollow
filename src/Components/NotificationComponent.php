<?php

namespace Webgraphe\Phollow\Components;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Webgraphe\Phollow\Tracer;

class NotificationComponent implements MessageComponentInterface
{
    /** @var \SplObjectStorage */
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
    public static function stringifyConnection(ConnectionInterface $conn)
    {
        return $conn->remoteAddress;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $this->tracer->info("New connection from " . self::stringifyConnection($conn));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        // do nothing
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $this->tracer->info("Connection closed " . self::stringifyConnection($conn));
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->tracer->error($e->getMessage());

        $conn->close();
    }
}
