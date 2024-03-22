<?php

namespace Worker\Workers;

use PhpAmqpLib\Message\AMQPMessage;
use Worker\Setup\RabbitMQSetup;
use Symfony\Component\Yaml\Yaml;
use Exception;

class CountryQueueWorker
{
    private RabbitMQSetup $rabbitMQSetup;
    private $channel;

    public function __construct()
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
                'country_queue', // Queue name
                ['durable' => false, 'exclusive' => false, 'auto_delete' => false] // Example queue options
            );
            $this->channel       = $this->rabbitMQSetup->getChannel();

            echo " [*] Waiting for messages in 'country_queue'. To exit press CTRL+C\n";
        } catch (Exception $e) {
            die("Error connecting to RabbitMQ: " . $e->getMessage() . "\n");
        }
    }

    public function startConsuming(): void
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
        $this->rabbitMQSetup->close();
    }

    /**
     * @throws Exception
     */
    private function fetchCapital(string $countryName): ?string
    {
        $url      = "https://restcountries.com/v3.1/name/" . urlencode($countryName);
        $response = file_get_contents($url);
        if ($response === false) {
            throw new Exception("API request failed");
        }

        $data = json_decode($response, true);
        return $data[0]['capital'][0] ?? null;
    }

    private function publishCapital(string $countryName, string $capital): void
    {
        $data = json_encode(['country_name' => $countryName, 'capital' => $capital]);
        $msg  = new AMQPMessage($data);
        $this->channel->basic_publish($msg, '', 'capital_queue');
        echo " [x] Sent capital info to 'capital_queue'\n";
    }
}
