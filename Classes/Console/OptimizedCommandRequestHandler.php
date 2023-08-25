<?php

declare(strict_types=1);

namespace Pixelant\Interest\Console;

use Symfony\Component\Console\Command\Command;
use TYPO3\CMS\Core\Console\CommandNameAlreadyInUseException;
use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\CMS\Core\Console\CommandRequestHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @see \TYPO3\CMS\Core\Console\CommandRequestHandler
 */
class OptimizedCommandRequestHandler extends CommandRequestHandler
{
    /**
     * Put all available commands inside the application. This implementation speeds up the process by only including
     * the executed command. This speeds up the request because Extbase's CoreCommand is not initializing Extbase.
     *
     * @throws CommandNameAlreadyInUseException
     */
    protected function populateAvailableCommands()
    {
        $commands = GeneralUtility::makeInstance(CommandRegistry::class);

        foreach ($commands as $commandName => $command) {
            if ($commandName === $GLOBALS['argv'][1] && !in_array($GLOBALS['argv'][1], ['help', 'list'])) {
                /** @var Command $command */
                $this->application->add($command);
            }
        }
    }
}
