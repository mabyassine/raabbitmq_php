<?php

namespace Worker\Workers;

use http\Exception\BadMessageException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Yaml\Yaml;
use Exception;

class CountryQueueWorker
{
    private $connection;
    private $channel;

    public function __construct()
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
            $this->channel = $this->connection->channel();

            // Declare a queue named 'country_queue' for receiving country names
            $this->channel->queue_declare('country_queue', false, false, false, false);

            echo " [*] Waiting for messages in 'country_queue'. To exit press CTRL+C\n";
        } catch (Exception $e) {
            die("Error connecting to RabbitMQ: " . $e->getMessage() . "\n");
        }
    }

    public function startConsuming()
    {
        $callback = function (AMQPMessage $msg) {
            $data        = json_decode($msg->body, true);
            $countryName = $data['country_name'] ?? null;
            if (!$countryName) {
                echo " [error] Country name is missing.\n";
                return;
            }

            echo " [x] Received request for country: $countryName\n";

            try {
                $capital = $this->fetchCapital($countryName);
                if ($capital !== null) {
                    $this->publishCapital($countryName, $capital);
                } else {
                    echo " [error] Capital not found for $countryName\n";
                }
            } catch (Exception $e) {
                echo " [error] Error fetching capital: " . $e->getMessage() . "\n";
            }
        };

        $this->channel->basic_consume('country_queue', '', false, true, false, false, $callback);

        while ($this->channel->is_consuming()) {
            try {
                $this->channel->wait();
            } catch (Exception $e) {
                echo "Error while waiting for messages: " . $e->getMessage() . "\n";
                break; // Exit the loop in case of error
            }
        }

        // Close the channel and connection when done
        $this->close();
    }

    private function fetchCapital(string $countryName): ?string
    {
        $url      = "https://restcountries.com/v3.1/name/" . urlencode($countryName);
        $response = file_get_contents($url);
        if ($response === false) {
            throw new BadMessageException("API request failed");
        }

        $data = json_decode($response, true);
        return $data[0]['capital'][0] ?? null;
    }

    private function publishCapital(string $countryName, string $capital)
    {
        $data = json_encode(['country_name' => $countryName, 'capital' => $capital]);
        $msg  = new AMQPMessage($data);
        $this->channel->basic_publish($msg, '', 'capital_queue');
        echo " [x] Sent capital info to 'capital_queue'\n";
    }

    public function close()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
