<?php

declare(strict_types=1);


namespace Pixelant\Interest\Command;

use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Command for deleting a record.
 */
class DeleteCommandController extends Command
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setDescription('Delete a record.')
            ->addArgument(
                'remoteId',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'The remote ID(s) of the records to delete.',
                []
            )
        ;
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var RemoteIdMappingRepository $mappingRepository */
        $mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        foreach ($input->getArgument('remoteId') as $remoteId) {


            new DeleteRecordOperation(
                [],
            );
        }
    }
}
