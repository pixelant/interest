<?php

declare(strict_types=1);

namespace Pixelant\Interest\Updates;

use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Install\Updates\ChattyInterface;

class RemovePendingRelationsWithEmptyRemoteIdUpdateWizard extends AbstractUpdateWizard implements ChattyInterface
{
    public const IDENTIFIER = 'interest_removePendingRelationsWithEmptyRemoteId';

    public const TITLE = 'Remove Invalid Pending Relations';

    public const DESCRIPTION = 'Removes pending relation records with empty remote IDs.';

    protected ?OutputInterface $output = null;

    /**
     * @inheritDoc
     */
    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    /**
     * @inheritDoc
     */
    public function executeUpdate(): bool
    {
        $queryBuilder = $this->getQueryBuilderForTable(PendingRelationsRepository::TABLE_NAME);

        $deletedCount = $queryBuilder
            ->delete(PendingRelationsRepository::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('remote_id', $queryBuilder->quote(''))
            )
            ->execute();

        if ($this->output !== null) {
            $this->output->writeln('Deleted ' . $deletedCount . ' pending relations with empty remote ID.');
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function updateNecessary(): bool
    {
        $queryBuilder = $this->getQueryBuilderForTable(PendingRelationsRepository::TABLE_NAME);

        return (bool)$queryBuilder
            ->count('*')
            ->from(PendingRelationsRepository::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('remote_id', $queryBuilder->quote(''))
            )
            ->execute()
            ->fetchOne();
    }

    /**
     * @inheritDoc
     */
    public function getPrerequisites(): array
    {
        return [];
    }
}
