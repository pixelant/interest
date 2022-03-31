<?php

declare(strict_types=1);

namespace Pixelant\Interest\Command;

use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ClearRecordHashCommandController extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setDescription(
                'Clear the record hash which stops Interest from running the same operation twice in a row. '
                . 'Clearing the hash means all requests will be run.'
            )
            ->addArgument(
                'remoteId',
                InputArgument::OPTIONAL,
                'The remote ID for which to clear the hash.'
            )
            ->addOption(
                'contains',
                'c',
                InputOption::VALUE_NONE,
                'The remoteId argument is a partial remote ID. Match all IDs containing the string.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queryBuilder = $this->getQueryBuilder();

        if ($input->getArgument('remoteId') !== null) {
            if ($input->getOption('contains')) {
                $queryBuilder->where($queryBuilder->expr()->like(
                    'remote_id',
                    $queryBuilder->createNamedParameter(
                        '%' . $queryBuilder->escapeLikeWildcards($input->getArgument('remoteId')) . '%'
                    )
                ));
            } else {
                $queryBuilder->where($queryBuilder->expr()->eq(
                    'remote_id',
                    $queryBuilder->createNamedParameter($input->getArgument('remoteId'))
                ));
            }
        }

        $rows = (int)$queryBuilder
            ->update(RemoteIdMappingRepository::TABLE_NAME)
            ->set('record_hash', '')
            ->execute();

        $output->writeln('Cleared hash in ' . $rows . ' rows.');

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
            ->getQueryBuilderForTable(RemoteIdMappingRepository::TABLE_NAME);
    }
}
