<?php

declare(strict_types=1);


namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\Exception\IdentityConflictException;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Handle a record creation operation.
 */
class CreateRecordOperation extends AbstractRecordOperation
{
    public function __construct(
        array $data,
        string $table,
        string $remoteId,
        ?string $language = null,
        ?string $workspace = null,
        ?array $metaData = []
    ) {
        if (GeneralUtility::makeInstance(RemoteIdMappingRepository::class)->exists($remoteId)) {
            throw new IdentityConflictException(
                'The remote ID "' . $remoteId . '" already exists.',
                1635780292790
            );
        }

        parent::__construct($data, $table, $remoteId, $language, $workspace, $metaData);

        $uid = $this->getUid() ?: StringUtility::getUniqueId('NEW');

        $this->dataHandler->datamap[$table][$uid] = $this->getData();
    }

    public function __destruct()
    {
        parent::__destruct();

        $this->pendingRelationsRepository->removeRemote($this->getRemoteId());
    }

    /**
     * Finds pending relations for a $remoteId record that is being inserted into the database and adds DataHandler
     * datamap array inserting any pending relations into the database as well.
     *
     * @param string|int $uid Could be a newly inserted UID or a temporary ID (e.g. NEW1234abcd)
     */
    protected function resolvePendingRelations($uid): void
    {
        foreach ($this->pendingRelationsRepository->get($this->getRemoteId()) as $pendingRelation) {
            /** @var RelationHandler $relationHandler */
            $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);

            $relationHandler->start(
                '',
                $this->getTable(),
                '',
                $pendingRelation['record_uid'],
                $pendingRelation['table'],
                $this->getTcaFieldConfigurationAndRespectColumnsOverrides($pendingRelation['field'])
            );

            $existingRelations = array_column(
                $relationHandler->getFromDB()[$pendingRelation['table']] ?? [],
                'uid'
            );

            $this->dataHandler->datamap[$pendingRelation['table']][$pendingRelation['record_uid']][$pendingRelation['field']]
                = implode(',', array_unique(array_merge($existingRelations, [$uid])));
        }
    }
}
