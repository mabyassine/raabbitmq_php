<?php

namespace Worker\Setup;

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQSetup
{
    private $connection;
    private $channel;

    /**
     * @throws Exception
     */
    public function __construct($host, $port, $user, $pass, $queueName, $queueOptions = [])
    {
        $this->setupRabbitMQ($host, $port, $user, $pass, $queueName, $queueOptions);
    }

    /**
     * @throws Exception
     */
    private function setupRabbitMQ($host, $port, $user, $pass, $queueName, $queueOptions): void
    {
        $this->connection = new AMQPStreamConnection($host, $port, $user, $pass);
        $this->channel    = $this->connection->channel();

        // Queue declaration
        $this->channel->queue_declare(
            $queueName,
            $queueOptions['passive'] ?? false,
            $queueOptions['durable'] ?? false,
            $queueOptions['exclusive'] ?? false,
            $queueOptions['auto_delete'] ?? false,
            $queueOptions['nowait'] ?? false,
            $queueOptions['arguments'] ?? [],
            $queueOptions['ticket'] ?? null
        );
    }

    public function getChannel()
    {
        return $this->channel;
    }

    public function close(): void
    {
        $this->channel->close();
        $this->connection->close();
    }
}
