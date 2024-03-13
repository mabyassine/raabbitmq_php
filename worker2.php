<?php
// Include autoload file from Composer to use external libraries
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

try {
    // Establish a connection to RabbitMQ server
    $connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
    $channel    = $connection->channel();

    // Declare a queue named 'capital_queue' for receiving capitals
    $channel->queue_declare('capital_queue', false, false, false, false);
} catch (Exception $e) {
    // Handle RabbitMQ connection errors
    die("Error connecting to RabbitMQ: " . $e->getMessage() . "\n");
}

// Path to SQLite database
$dbPath = __DIR__ . '/capitals.db';
try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    // Handle SQLite connection errors
    die("Error connecting to SQLite database: " . $e->getMessage() . "\n");
}

// Function to initialize or verify the SQLite database and table
function initializeDatabase(PDO $pdo)
{
    $createTableQuery = "CREATE TABLE IF NOT EXISTS capitals (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            country_name TEXT NOT NULL,
                            capital_name TEXT NOT NULL
                         )";
    try {
        $pdo->exec($createTableQuery);
    } catch (Exception $e) {
        // Handle errors during table creation
        die("Error creating table: " . $e->getMessage() . "\n");
    }
}

// Initialize or verify the SQLite database and table
initializeDatabase($pdo);

echo " [*] Waiting for capital messages in 'capital_queue'. To exit press CTRL+C\n";

// Callback function to process received messages and save them to the database
$callback = function (AMQPMessage $msg) use ($pdo) {
    $data        = json_decode($msg->body, true);
    $countryName = $data['country_name'] ?? null;
    $capital     = $data['capital'] ?? null;

    if (!$countryName || !$capital) {
        echo " [error] Missing country name or capital.\n";
        return;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO capitals (country_name, capital_name) VALUES (?, ?)");
        $stmt->execute([$countryName, $capital]);
        echo " [x] Saved $capital for country $countryName in the database\n";
    } catch (Exception $e) {
        echo " [error] Database error: " . $e->getMessage() . "\n";
    }
};

// Start consuming messages from 'capital_queue'
try {
    $channel->basic_consume('capital_queue', '', false, true, false, false, $callback);
} catch (Exception $e) {
    // Handle errors during message consumption setup
    echo "Error setting up consumer: " . $e->getMessage() . "\n";
}

// Wait for messages until the script is stopped
while ($channel->is_consuming()) {
    try {
        $channel->wait();
    } catch (Exception $e) {
        // Handle errors during message waiting
        echo "Error while waiting for messages: " . $e->getMessage() . "\n";
        break; // Exit the loop in case of error
    }
}

// Close the channel and connection when done
$channel->close();
$connection->close();
