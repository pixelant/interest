<?php

declare(strict_types=1);

namespace Pixelant\Interest\Command;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
use Pixelant\Interest\DataHandling\Operation\Exception\NotFoundException;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Model\Dto\RecordInstanceIdentifier;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for updating a record.
 */
class UpdateCommandController extends AbstractReceiveCommandController
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setDescription('Update a record.')
            ->addOption(
                'create',
                'c',
                InputOption::VALUE_NONE,
                'Quietly create the record if it doesn\'t already exist.'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     *
     * @throws NotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $exceptions = [];

        foreach ($input->getOption('data') as $remoteId => $data) {
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
            } catch (NotFoundException $exception) {
                if (!$input->getOption('create')) {
                    throw $exception;
                }

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
