<?php

declare(strict_types=1);

namespace Pixelant\Interest\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Bootstrap;

/**
 * Abstract class for handling incoming data.
 */
abstract class AbstractReceiveCommandController extends AbstractRecordCommandController
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->addArgument(
                'endpoint',
                InputArgument::REQUIRED,
                'The endpoint. Usually a table name, e.g. "tt_content".'
            )
            ->addArgument(
                'remoteId',
                InputArgument::REQUIRED,
                'The remote ID for the data.'
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
            );
    }

    /**
     * @inheritDoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        Bootstrap::initializeBackendAuthentication();
    }
}
