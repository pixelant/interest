<?php

declare(strict_types=1);

namespace Pixelant\Interest\Updates;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

abstract class AbstractUpdateWizard implements UpgradeWizardInterface
{
    public const IDENTIFIER = '';

    public const TITLE = '';

    public const DESCRIPTION = '';

    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return self::TITLE;
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return self::DESCRIPTION;
    }

    /**
     * @param string $table
     * @return QueryBuilder
     */
    protected function getQueryBuilderForTable(string $table): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
    }
}
