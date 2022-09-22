<?php

declare(strict_types=1);

namespace Pixelant\Interest\Command;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
use Pixelant\Interest\DataHandling\Operation\Exception\IdentityConflictException;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Model\Dto\RecordInstanceIdentifier;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     *
     * @throws IdentityConflictException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $exceptions = [];

        foreach ($input->getOption('data') as $remoteId => $data) {
            try {
                (new CreateRecordOperation(
                    new RecordRepresentation(
                        $data,
                        new RecordInstanceIdentifier(
                            $input->getArgument('endpoint'),
                            $remoteId,
                            $input->getArgument('language'),
                            $input->getArgument('workspace'),
                        )
                    ),
                    $input->getOption('metaData')
                ))();
            } catch (StopRecordOperationException $exception) {
                $output->writeln($exception->getMessage(), OutputInterface::VERBOSITY_VERY_VERBOSE);

                continue;
            } catch (IdentityConflictException $exception) {
                if (!$input->getOption('update')) {
                    throw $exception;
                }

                try {
                    (new UpdateRecordOperation(
                        new RecordRepresentation(
                            $data,
                            new RecordInstanceIdentifier(
                                $input->getArgument('endpoint'),
                                $remoteId,
                                $input->getArgument('language'),
                                $input->getArgument('workspace'),
                            )
                        ),
                        $input->getOption('metaData')
                    ))();
                } catch (StopRecordOperationException $exception) {
                    $output->writeln($exception->getMessage(), OutputInterface::VERBOSITY_VERY_VERBOSE);

                    continue;
                }
            } catch (\Throwable $exception) {
                $exceptions[] = $exception;
            }
        }

        if (count($exceptions) > 0) {
            foreach ($exceptions as $exception) {
                $this->getApplication()->renderThrowable($exception, $output);
            }

            return 255;
        }

        return 0;
    }
}
