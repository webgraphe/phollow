<?php

namespace Webgraphe\Phollow\Components;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Webgraphe\Phollow\Tracer;

class LogComponent implements MessageComponentInterface
{
    /** @var Tracer */
    private $tracer;
    /** @var WritableLogStream */
    private $writableLogStream;

    public function __construct(Tracer $tracer, WritableLogStream $writableLogStream)
    {
        $this->tracer = $tracer;
        $this->writableLogStream = $writableLogStream;
    }

    /**
     * @param ConnectionInterface $conn
     * @return false|string
     */
    public static function stringifyConnection(ConnectionInterface $conn)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        return $conn->remoteAddress ?: '#' . $conn->resourceId;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->tracer->notice(self::stringifyConnection($conn) . ' Connected');
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $this->writableLogStream->write($msg);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->tracer->notice(self::stringifyConnection($conn) . ' Disconnected');
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->tracer->error($e->getMessage());

        $conn->close();
    }
}
