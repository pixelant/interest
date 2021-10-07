<?php

declare(strict_types=1);

namespace Pixelant\Interest\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
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
