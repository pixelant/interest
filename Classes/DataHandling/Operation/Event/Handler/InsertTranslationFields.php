<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Utility\TcaUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Create the translation fields if the table is translatable, language is set and nonzero, and the language field
 * hasn't already been set.
 */
class InsertTranslationFields implements RecordOperationEventHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(AbstractRecordOperationEvent $event): void
    {
        $recordOperation = $event->getRecordOperation();

        if (
            $recordOperation->getLanguage() === null
            || $recordOperation->getLanguage()->getLanguageId() === 0
            || !TcaUtility::isLocalizable($recordOperation->getTable())
            || $recordOperation->isDataFieldSet(TcaUtility::getLanguageField($recordOperation->getTable()))
        ) {
            return;
        }

        $mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        $baseLanguageRemoteId = $mappingRepository->removeAspectsFromRemoteId($recordOperation->getRemoteId());

        $recordOperation->setDataFieldForDataHandler(
            TcaUtility::getLanguageField($recordOperation->getTable()),
            $recordOperation->getLanguage()->getLanguageId()
        );

        $transOrigPointerField = TcaUtility::getTransOrigPointerField($recordOperation->getTable());

        if (
            ($transOrigPointerField ?? '') !== ''
            && !$recordOperation->isDataFieldSet($transOrigPointerField)
        ) {
            $recordOperation->setDataFieldForDataHandler($transOrigPointerField, $baseLanguageRemoteId);
        }

        $translationSourceField = TcaUtility::getTranslationSourceField($recordOperation->getTable());

        if (
            ($translationSourceField ?? '') !== ''
            && $recordOperation->isDataFieldSet($translationSourceField)
        ) {
            $recordOperation->setDataFieldForDataHandler($translationSourceField, $baseLanguageRemoteId);
        }
    }
}
