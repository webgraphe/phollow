<?php

namespace Webgraphe\Phollow\Components;

use Ratchet\ConnectionInterface;

class LogMessage
{
    /** @var ConnectionInterface */
    private $connection;
    /** @var string */
    private $message;

    /**
     * @param ConnectionInterface $connection
     * @param string $message
     */
    public function __construct(ConnectionInterface $connection, $message)
    {
        $this->connection = $connection;
        $this->message = $message;
    }

    /**
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }
}
