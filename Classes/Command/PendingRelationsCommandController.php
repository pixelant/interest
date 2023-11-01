<?php

declare(strict_types=1);

namespace Pixelant\Interest\Command;

use Doctrine\DBAL\Driver\Result;
use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\Domain\Repository\Exception\InvalidQueryResultException;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Utility\RelationUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PendingRelationsCommandController extends Command
{
    /**
     * @inheritDoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        Bootstrap::initializeBackendAuthentication();
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->setDescription('View statistics or process pending relations')
            ->addOption(
                'resolve',
                'r',
                InputOption::VALUE_NONE,
                'Attempt to resolve pending relations where both sides exist.',
                false
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws InvalidQueryResultException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $counts = [
            '_total' => [
                'count' => 0,
                'resolvable' => 0,
            ],
        ];

        $queryBuilder = $this->getQueryBuilder();

        $counts['_total']['count'] = $queryBuilder
            ->count('*')->from(PendingRelationsRepository::TABLE_NAME)->executeQuery()
            ->fetchOne();

        if ($counts['_total']['count'] === 0) {
            $output->writeln('<info>No pending relations found.</info>');

            return 0;
        }

        $queryBuilder = $this->getQueryBuilder();

        $counts['_total']['resolvable'] = $queryBuilder
            ->count('*')
            ->from(PendingRelationsRepository::TABLE_NAME, 'p')
            ->join(
                'p',
                RemoteIdMappingRepository::TABLE_NAME,
                'm',
                $queryBuilder->expr()->eq(
                    'p.remote_id',
                    $queryBuilder->quoteIdentifier('m.remote_id')
                )
            )
            ->executeQuery()
            ->fetchOne();

        $queryBuilder = $this->getQueryBuilder();

        $tables = array_column(
            $queryBuilder
                ->select('table')
                ->from(PendingRelationsRepository::TABLE_NAME)->groupBy('table')->executeQuery()
                ->fetchAllNumeric(),
            'table'
        );

        $rows = [];

        $rows[] = [
            'TOTAL',
            $counts['_total']['count'],
            $this->formatRedIfNonzero($counts['_total']['resolvable']),
        ];

        foreach ($tables as $table) {
            $counts[$table] = [];

            $queryBuilder = $this->getQueryBuilder();

            $counts[$table]['count'] = (int)$queryBuilder
                ->count('*')
                ->from(PendingRelationsRepository::TABLE_NAME)
                ->where(
                    $queryBuilder->expr()->eq(
                        'table',
                        $queryBuilder->createNamedParameter($table)
                    )
                )
                ->executeQuery()
                ->fetchFirstColumn();

            $queryBuilder = $this->getQueryBuilder();

            $counts[$table]['resolvable'] = (int)$queryBuilder
                ->count('*')
                ->from(PendingRelationsRepository::TABLE_NAME, 'p')
                ->join(
                    'p',
                    RemoteIdMappingRepository::TABLE_NAME,
                    'm',
                    $queryBuilder->expr()->eq(
                        'p.remote_id',
                        $queryBuilder->quoteIdentifier('m.remote_id')
                    )
                )
                ->where(
                    $queryBuilder->expr()->eq(
                        'p.table',
                        $queryBuilder->createNamedParameter($table)
                    )
                )
                ->executeQuery()
                ->fetchFirstColumn();

            $rows[] = [
                $table,
                $counts[$table]['count'],
                $this->formatRedIfNonzero($counts[$table]['resolvable']),
            ];
        }

        $table = new Table($output);

        $table->setHeaders(['Table', 'Pending', 'Resolvable']);
        $table->setRows($rows);

        $table->render();

        if ($input->getOption('resolve') === false) {
            return 0;
        }

        $queryBuilder = $this->getQueryBuilder();

        $resolvableRelations = $queryBuilder
            ->select('p.*', 'm.table as _foreign_table', 'm.uid_local as _foreign_uid')
            ->from(PendingRelationsRepository::TABLE_NAME, 'p')
            ->join(
                'p',
                RemoteIdMappingRepository::TABLE_NAME,
                'm',
                $queryBuilder->expr()->eq(
                    'p.remote_id',
                    $queryBuilder->quoteIdentifier('m.remote_id')
                )
            )
            ->executeQuery();

        if (!($resolvableRelations instanceof Result)) {
            throw new InvalidQueryResultException(
                'Query result was not an instance of ' . Result::class,
                1648879655137
            );
        }

        $progressBar = new ProgressBar($output);
        $progressBar->setMaxSteps($counts['_total']['resolvable']);

        /** @var PendingRelationsRepository $pendingRelationsRepository */
        $pendingRelationsRepository = GeneralUtility::makeInstance(PendingRelationsRepository::class);

        while (true) {
            $resolvableRelation = $resolvableRelations->fetchAssociative();

            if (!$resolvableRelation) {
                break;
            }

            // Recreate DataHandler each time. This can probably by optimized by running e.g. 50 changes at a time,
            // but with thousands of changes we shouldn't run them all in one operation due to memory concerns.
            /** @var DataHandler $dataHandler */
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);

            $dataHandler->start([], []);

            RelationUtility::addResolvedPendingRelationToDataHandler(
                $dataHandler,
                $resolvableRelation,
                $resolvableRelation['_foreign_table'],
                $resolvableRelation['_foreign_uid']
            );

            $output->writeln(
                var_export([
                    'resolvableRelation' => $resolvableRelation,
                    'datamap' => $dataHandler->datamap,
                ], true),
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );

            $dataHandler->process_datamap();

            if (count($dataHandler->errorLog) > 0) {
                foreach ($dataHandler->errorLog as $error) {
                    $output->writeln('<error>' . $error . '</error>');
                }

                return 1;
            }

            $pendingRelationsRepository->removeLocal(
                $resolvableRelation['table'],
                $resolvableRelation['field'],
                $resolvableRelation['record_uid']
            );

            $progressBar->advance();
        }

        $progressBar->finish();

        return 0;
    }

    /**
     * Returns a QueryBuilder for self::TABLE_NAME.
     *
     * @return QueryBuilder
     */
    protected function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(PendingRelationsRepository::TABLE_NAME);
    }

    protected function formatRedIfNonzero(int $number): string
    {
        if ($number === 0) {
            return (string)$number;
        }

        return '<fg=red>' . $number . '</>';
    }
}
