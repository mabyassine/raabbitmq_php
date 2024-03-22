<?php

namespace Worker\Workers;

use PhpAmqpLib\Message\AMQPMessage;
use PDO;
use Exception;
use Worker\Setup\RabbitMQSetup;
use Symfony\Component\Yaml\Yaml;

class CapitalQueueConsumer
{
    private RabbitMQSetup $rabbitMQSetup;
    private $channel;
    private $pdo;

    public function __construct()
    {
        $this->setupRabbitMQ();
        $this->setupDatabase();
        $this->initializeDatabase();
    }

    private function setupRabbitMQ(): void
    {
        try {
            // Load configurations from YAML file
            $config         = Yaml::parseFile(__DIR__ . '/../../rabbitmq.yml');
            $rabbitmqConfig = $config['rabbitmq'];

            // Initialize RabbitMQSetup with configurations and queue declaration
            $this->rabbitMQSetup = new RabbitMQSetup(
                $rabbitmqConfig['host'],
                $rabbitmqConfig['port'],
                $rabbitmqConfig['user'],
                $rabbitmqConfig['pass'],
                'capital_queue', // Queue name
                ['durable' => false, 'exclusive' => false, 'auto_delete' => false] // Example queue options
            );
            $this->channel       = $this->rabbitMQSetup->getChannel();

            echo " [*] Waiting for messages in 'capital_queue'. To exit press CTRL+C\n";
        } catch (Exception $e) {
            die("Error connecting to RabbitMQ: " . $e->getMessage() . "\n");
        }
    }

    private function setupDatabase(): void
    {
        $dbPath = __DIR__ . '/../../capitals.db';
        try {
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            die("Error connecting to SQLite database: " . $e->getMessage() . "\n");
        }
    }

    private function initializeDatabase(): void
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

    public function startConsuming(): void
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

        $this->rabbitMQSetup->close();
    }
}
