<?php

namespace Worker\Workers;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PDO;
use Exception;
use Symfony\Component\Yaml\Yaml;

class CapitalQueueConsumer
{
    private $connection;
    private $channel;
    private $pdo;

    public function __construct()
    {
        $this->setupRabbitMQ();
        $this->setupDatabase();
        $this->initializeDatabase();
    }

    private function setupRabbitMQ()
    {
        // Load configurations from YAML file
        $config = Yaml::parseFile(__DIR__ . '/../../rabbitmq.yml');
        $rabbitmqConfig = $config['rabbitmq'];

        try {
            // Establish a connection to RabbitMQ server using configuration
            $this->connection = new AMQPStreamConnection(
                $rabbitmqConfig['host'],
                $rabbitmqConfig['port'],
                $rabbitmqConfig['user'],
                $rabbitmqConfig['pass']
            );
            $this->channel    = $this->connection->channel();

            // Declare a queue named 'country_queue' for receiving country names
            $this->channel->queue_declare('country_queue', false, false, false, false);

            echo " [*] Waiting for messages in 'country_queue'. To exit press CTRL+C\n";
        } catch (Exception $e) {
            die("Error connecting to RabbitMQ: " . $e->getMessage() . "\n");
        }
    }

    private function setupDatabase()
    {
        $dbPath = __DIR__ . '/../../capitals.db';
        try {
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            die("Error connecting to SQLite database: " . $e->getMessage() . "\n");
        }
    }

    private function initializeDatabase()
    {
        $createTableQuery = "CREATE TABLE IF NOT EXISTS capitals (
                                id INTEGER PRIMARY KEY AUTOINCREMENT,
                                country_name TEXT NOT NULL,
                                capital_name TEXT NOT NULL
                             )";
        try {
            $this->pdo->exec($createTableQuery);
        } catch (Exception $e) {
            die("Error creating table: " . $e->getMessage() . "\n");
        }
    }

    public function startConsuming()
    {
        $callback = function (AMQPMessage $msg) {
            $data        = json_decode($msg->body, true);
            $countryName = $data['country_name'] ?? null;
            $capital     = $data['capital'] ?? null;

            if (!$countryName || !$capital) {
                echo " [error] Missing country name or capital.\n";
                return;
            }

            try {
                $stmt = $this->pdo->prepare("INSERT INTO capitals (country_name, capital_name) VALUES (?, ?)");
                $stmt->execute([$countryName, $capital]);
                echo " [x] Saved $capital for country $countryName in the database\n";
            } catch (Exception $e) {
                echo " [error] Database error: " . $e->getMessage() . "\n";
            }
        };

        $this->channel->basic_consume('capital_queue', '', false, true, false, false, $callback);

        while ($this->channel->is_consuming()) {
            try {
                $this->channel->wait();
            } catch (Exception $e) {
                echo "Error while waiting for messages: " . $e->getMessage() . "\n";
                break; // Exit the loop in case of error
            }
        }

        $this->close();
    }

    public function close()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
