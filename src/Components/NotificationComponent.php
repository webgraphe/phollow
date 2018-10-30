<?php

namespace Webgraphe\Phollow\Components;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class NotificationComponent implements MessageComponentInterface
{
    protected $clients;

    public function __construct()
    {
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
        echo "New connection from " . self::stringifyConnection($conn) . PHP_EOL;
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        // do nothing
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Connection closed " . self::stringifyConnection($conn) . PHP_EOL;
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }
}
