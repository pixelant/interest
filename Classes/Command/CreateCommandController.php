<?php

declare(strict_types=1);


namespace Pixelant\Interest\Command;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for creating records.
 */
class CreateCommandController extends AbstractReceiveCommandController
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setDescription('Create a new record.')
            ->addOption(
                'update',
                'u',
                InputOption::VALUE_NONE,
                'Quietly update the record if it already exists.'
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        new CreateRecordOperation(
            $input->getOption('data'),
            $input->getArgument('endpoint'),
            $input->getArgument('remoteId'),
            $input->getArgument('language'),
            $input->getArgument('workspace'),
            $input->getOption('metaData')
        );

        return 0;
    }
}
