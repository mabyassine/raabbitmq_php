<?php
// Include autoload file from Composer to use external libraries
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

try {
    // Establish a connection to RabbitMQ server
    $connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
    $channel    = $connection->channel();

    // Declare a queue named 'country_queue' for receiving country names
    $channel->queue_declare('country_queue', false, false, false, false);
} catch (Exception $e) {
    // Handle RabbitMQ connection errors
    die("Error connecting to RabbitMQ: " . $e->getMessage() . "\n");
}

echo " [*] Waiting for messages in 'country_queue'. To exit press CTRL+C\n";

// Callback function to process received messages
$callback = function (AMQPMessage $msg) {
    $data        = json_decode($msg->body, true);
    $countryName = $data['country_name'] ?? null;
    if (!$countryName) {
        echo " [error] Country name is missing.\n";
        return;
    }

    echo " [x] Received request for country: $countryName\n";

    try {
        $capital = fetchCapital($countryName);
        if ($capital !== null) {
            publishCapital($countryName, $capital);
        } else {
            echo " [error] Capital not found for $countryName\n";
        }
    } catch (Exception $e) {
        echo " [error] Error fetching capital: " . $e->getMessage() . "\n";
    }
};

$channel->basic_consume('country_queue', '', false, true, false, false, $callback);

// Function to fetch the capital of a country using the RestCountries API
function fetchCapital(string $countryName): ?string
{
    $url      = "https://restcountries.com/v3.1/name/" . urlencode($countryName);
    $response = file_get_contents($url);
    if ($response === false) {
        throw new Exception("API request failed");
    }

    $data = json_decode($response, true);
    return $data[0]['capital'][0] ?? null;
}

// Function to publish the fetched capital to 'capital_queue'
function publishCapital(string $countryName, string $capital)
{
    global $channel;
    $data = json_encode(['country_name' => $countryName, 'capital' => $capital]);
    $msg  = new AMQPMessage($data);
    $channel->basic_publish($msg, '', 'capital_queue');
    echo " [x] Sent capital info to 'capital_queue'\n";
}

while ($channel->is_consuming()) {
    try {
        $channel->wait();
    } catch (Exception $e) {
        echo "Error while waiting for messages: " . $e->getMessage() . "\n";
        break; // Exit the loop in case of error
    }
}

// Close the channel and connection when done
$channel->close();
$connection->close();
