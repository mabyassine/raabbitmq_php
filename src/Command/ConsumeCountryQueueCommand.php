<?php

namespace Worker\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Worker\Workers\CountryQueueWorker;

#[AsCommand(name: 'consume:country-queue')]
class ConsumeCountryQueueCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Starts the consumer to process messages from the country_queue.')
            ->setHelp('This command allows you to start the consumer that reads messages from the country_queue and processes them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln([
            'Country Queue Consumer',
            '======================',
            'Starting...',
        ]);

        try {
            $worker = new CountryQueueWorker();
            $worker->startConsuming();
        } catch (\Exception $e) {
            $output->writeln('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
