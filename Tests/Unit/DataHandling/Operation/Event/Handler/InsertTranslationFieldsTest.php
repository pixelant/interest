<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\InsertTranslationFields;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationSetupEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class InsertTranslationFieldsTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    protected array $classNames = [
        CreateRecordOperation::class,
        DeleteRecordOperation::class,
        UpdateRecordOperation::class,
    ];

    /**
     * @test
     */
    public function returnsEarlyIfLanguageIsNull()
    {
        foreach ($this->classNames as $className) {
            $mockOperation = $this->createMock($className);

            $mockOperation
                ->method('getLanguage')
                ->willReturn(null);

            $mockOperation
                ->expects(self::never())
                ->method('setDataFieldForDataHandler');

            $event = new RecordOperationSetupEvent($mockOperation);

            (new InsertTranslationFields())($event);
        }
    }

    /**
     * @test
     */
    public function returnsEarlyIfLanguageIsZero()
    {
        foreach ($this->classNames as $className) {
            $mockLanguage = $this->createMock(SiteLanguage::class);

            $mockLanguage
                ->method('getLanguageId')
                ->willReturn(0);

            $mockOperation = $this->createMock($className);

            $mockOperation
                ->method('getLanguage')
                ->willReturn($mockLanguage);

            $mockOperation
                ->expects(self::never())
                ->method('setDataFieldForDataHandler');

            $event = new RecordOperationSetupEvent($mockOperation);

            (new InsertTranslationFields())($event);
        }
    }

    /**
     * @test
     */
    public function returnsEarlyIfTableNotTranslatable()
    {
        foreach ($this->classNames as $className) {
            $mockOperation = $this->createMock($className);

            $mockOperation
                ->method('getLanguage')
                ->willReturn(null);

            $mockOperation
                ->method('getTable')
                ->willReturn('tablename');

            $mockOperation
                ->expects(self::never())
                ->method('setDataFieldForDataHandler');

            $event = new RecordOperationSetupEvent($mockOperation);

            (new InsertTranslationFields())($event);
        }
    }

    /**
     * @test
     */
    public function returnsEarlyIfLanguageFieldIsSet()
    {
        foreach ($this->classNames as $className) {
            $mockOperation = $this->createMock($className);

            $mockOperation
                ->method('getLanguage')
                ->willReturn(null);

            $mockOperation
                ->method('getTable')
                ->willReturn('tablename');

            $mockOperation
                ->method('isDataFieldSet')
                ->willReturn(true);

            $mockOperation
                ->expects(self::never())
                ->method('setDataFieldForDataHandler');

            $event = new RecordOperationSetupEvent($mockOperation);

            (new InsertTranslationFields())($event);
        }
    }

    /**
     * @test
     *
     * @dataProvider provideDataForInsertsCorrectTranslationFields
     */
    public function insertsCorrectTranslationFields(callable $configureTca, array $setDataFieldForDataHandlerExpects)
    {
        $configureTca();

        $mockLanguage = $this->createMock(SiteLanguage::class);

        $mockLanguage
            ->method('getLanguageId')
            ->willReturn(12);

        $mockMappingRepository = $this->createMock(RemoteIdMappingRepository::class);

        $mockMappingRepository
            ->method('removeAspectsFromRemoteId')
            ->willReturn('baseLanguageRemoteId');

        GeneralUtility::setSingletonInstance(RemoteIdMappingRepository::class, $mockMappingRepository);

        foreach ($this->classNames as $className) {
            $mockOperation = $this->createMock($className);

            $mockOperation
                ->method('getLanguage')
                ->willReturn($mockLanguage);

            $mockOperation
                ->method('getTable')
                ->willReturn('tablename');

            $mockOperation
                ->expects(self::exactly(count($setDataFieldForDataHandlerExpects)))
                ->method('isDataFieldSet')
                ->willReturnOnConsecutiveCalls(
                    ...array_fill(0, count($setDataFieldForDataHandlerExpects), false)
                );

            $mockOperation
                ->expects(self::exactly(count($setDataFieldForDataHandlerExpects)))
                ->method('setDataFieldForDataHandler')
                ->withConsecutive(...$setDataFieldForDataHandlerExpects);

            $event = new RecordOperationSetupEvent($mockOperation);

            (new InsertTranslationFields())($event);
        }
    }

    public function provideDataForInsertsCorrectTranslationFields(): array
    {
        return [
            [
                function () {
                    $GLOBALS['TCA']['tablename']['ctrl'] = [
                        'languageField' => 'languageField1',
                    ];
                },
                [
                    ['languageField1', 12],
                ],
            ],
            [
                function () {
                    $GLOBALS['TCA']['tablename']['ctrl'] = [
                        'languageField' => 'languageField2',
                        'transOrigPointerField' => 'transOrigPointerField2',
                    ];
                },
                [
                    ['languageField2', 12],
                    ['transOrigPointerField2', 'baseLanguageRemoteId'],
                ],
            ],
            [
                function () {
                    $GLOBALS['TCA']['tablename']['ctrl'] = [
                        'languageField' => 'languageField3',
                        'transOrigPointerField' => 'transOrigPointerField3',
                        'translationSource' => 'translationSource3',
                    ];
                },
                [
                    ['languageField3', 12],
                    ['transOrigPointerField3', 'baseLanguageRemoteId'],
                    ['translationSource3', 'baseLanguageRemoteId'],
                ],
            ],
        ];
    }
}
