
# RabbitMQ PHP Project Overview

This PHP project demonstrates asynchronous communication using RabbitMQ, focusing on message queuing between two main workers: one that handles countries and another for capitals. It includes the use of Docker for environment management and SQLite for data storage.

## Project Components

- **Country Consumer ([CountryQueueWorker.php](src%2FWorkers%2FCountryQueueWorker.php) Worker 1):** Listens for messages in the `country_queue`, fetches the capital of the country using the RestCountries API, and publishes the result to `capital_queue`.
- **Capital Consumer ([CapitalQueueConsumer.php](src%2FWorkers%2FCapitalQueueConsumer.php) Worker 2):** Listens for messages in the `capital_queue` and stores the received capital information into a SQLite database.

## Prerequisites

- Docker and Docker Compose
- PHP 7.4 or newer
- Composer for PHP dependency management
- RabbitMQ server
- SQLite for database storage

## Setup Instructions

1. **Clone the repository:** Start by cloning the project to your local machine.
2. **Install dependencies:** Navigate to the project directory and run:
    ```bash
    composer install
    ```
3. **Start RabbitMQ server:** Use Docker Compose to start a local RabbitMQ server:
    ```bash
    docker-compose up --build -d
    ```
   This will also set up any other necessary services defined in your `docker-compose.yml` file.
## Running the Application

- **Running Workers:** To start the workers, use the commands:
    - For Country Consumer (Worker 1): ` ./console consume:country-queue`
    - For Capital Consumer (Worker 2): ` ./console consume:country-queue`

## Testing

- **PHPUnit Tests:** Ensure PHPUnit is installed, and execute tests with:
    ```bash
    vendor/bin/phpunit
    ```
- **Docker Environment Testing:** After building and starting the containers, verify that the PHP producer and consumer scripts can successfully communicate with the RabbitMQ server.

## Additional Information

- **RabbitMQ Management Interface:** Accessible at `http://localhost:15672` with the default username and password (`guest`/`guest`), where you can monitor queues, messages, and more.
- **Error Handling:** Basic error handling is implemented within the workers. Extend as needed for more robust production applications.

## Contributing

Contributions to enhance the functionality, documentation, or setup process are welcome. Please adhere to conventional pull request processes for any contributions.