<?php

declare(strict_types=1);


namespace Pixelant\Interest\Command;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
use Pixelant\Interest\DataHandling\Operation\Exception\IdentityConflictException;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
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
        try {
            new CreateRecordOperation(
                $input->getOption('data'),
                $input->getArgument('endpoint'),
                $input->getArgument('remoteId'),
                $input->getArgument('language'),
                $input->getArgument('workspace'),
                $input->getOption('metaData')
            );
        } catch (StopRecordOperationException $exception) {
            $output->writeln($exception->getMessage(), OutputInterface::VERBOSITY_VERY_VERBOSE);

            return 0;
        } catch (IdentityConflictException $exception) {
            if (!$input->getOption('update')) {
                throw $exception;
            }

            new UpdateRecordOperation(
                $input->getOption('data'),
                $input->getArgument('endpoint'),
                $input->getArgument('remoteId'),
                $input->getArgument('language'),
                $input->getArgument('workspace'),
                $input->getOption('metaData')
            );
        }

        return 0;
    }
}
