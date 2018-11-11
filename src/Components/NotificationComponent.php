<?php

namespace Webgraphe\Phollow\Components;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Webgraphe\Phollow\Documents;
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
    public static function stringifyConnection(ConnectionInterface $conn)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        return $conn->remoteAddress;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $this->tracer->notice(self::stringifyConnection($conn) . ' Connected');
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $this->tracer->info(self::stringifyConnection($from) . " (ignored) $msg");
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $this->tracer->notice(self::stringifyConnection($conn) . ' Disconnected');
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->tracer->error($e->getMessage());

        $conn->close();
    }

    public function notifyNewError(Documents\Error $error)
    {
        foreach ($this->clients as $client) {
            $client->send(json_encode($error));
        }
    }
}
