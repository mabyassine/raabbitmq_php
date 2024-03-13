# RabbitMQ PHP Project

This project consists of two PHP workers for asynchronous communication using RabbitMQ and includes a SQLite database for storing capitals data.

## Setup

1. Ensure RabbitMQ is installed and running on your system.
2. Install PHP and Composer.
3. Run `composer require php-amqplib/php-amqplib` to install the required library.
4. Create a SQLite database by running the SQL commands in `create_database.sql`.

## Worker 1: Country Consumer

- Listens for messages in the `country_queue`.
- Fetches the capital of the country using the RestCountries API.
- Publishes the country and capital to `capital_queue`.

## Worker 2: Capital Consumer

- Listens for messages in the `capital_queue`.
- Saves the received capital information into a SQLite database.

## Running the Workers

To run the workers, use the following commands in your terminal:

- For worker 1: `php worker1.php`
- For worker 2: `php worker2.php`

## Error Handling

Error handling is implemented rudimentarily. Extend as required for production use.
