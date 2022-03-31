<?php

declare(strict_types=1);

namespace Pixelant\Interest\Command;

use Pixelant\Interest\Context;
use Pixelant\Interest\Database\RelationHandlerWithoutReferenceIndex;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\RelationHandler;
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
                InputArgument::REQUIRED,
                'Comma-separated list of the remote ID(s) of the records to delete.'
            )
            ->addArgument(
                'language',
                InputArgument::OPTIONAL,
                'RFC 1766/3066 string, e.g. "nb" or "sv-SE".'
            )
            ->addArgument(
                'workspace',
                InputArgument::OPTIONAL,
                'Not yet implemented.'
            )
            ->addOption(
                'disableReferenceIndex',
                null,
                InputOption::VALUE_NONE,
                'If set, the reference index will not be updated.'
            );
    }

    /**
     * @inheritDoc
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        Bootstrap::initializeBackendAuthentication();

        Context::setDisableReferenceIndex($input->getOption('disableReferenceIndex'));

        if (Context::isDisableReferenceIndex()) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][RelationHandler::class] = [
                'className' => RelationHandlerWithoutReferenceIndex::class,
            ];
        }
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $exceptions = [];

        foreach (GeneralUtility::trimExplode(',', $input->getArgument('remoteId'), true) as $remoteId) {
            try {
                (new DeleteRecordOperation(
                    $remoteId,
                    $input->getArgument('language'),
                    $input->getArgument('workspace')
                ))();
            } catch (StopRecordOperationException $exception) {
                $output->writeln($exception->getMessage(), OutputInterface::VERBOSITY_VERY_VERBOSE);

                continue;
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
