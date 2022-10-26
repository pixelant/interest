<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation;

use Pixelant\Interest\Configuration\ConfigurationProvider;
use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\Domain\Model\Dto\RecordInstanceIdentifier;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class CreateRecordOperationTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSingletonInstances = true;

        GeneralUtility::setSingletonInstance(
            ConfigurationProvider::class,
            $this->createMock(ConfigurationProvider::class)
        );

        GeneralUtility::setSingletonInstance(
            RemoteIdMappingRepository::class,
            $this->createMock(RemoteIdMappingRepository::class)
        );

        GeneralUtility::setSingletonInstance(
            PendingRelationsRepository::class,
            $this->createMock(PendingRelationsRepository::class)
        );

        $eventDispatcherMock = $this->createMock(EventDispatcher::class);

        $eventDispatcherMock
            ->method('dispatch')
            ->willReturnArgument(0);

        GeneralUtility::setSingletonInstance(
            EventDispatcher::class,
            $eventDispatcherMock
        );

        GeneralUtility::addInstance(
            DataHandler::class,
            $this->createMock(DataHandler::class)
        );
    }

    /**
     * @test
     */
    public function resolveStoragePidReturnsZeroIfRootLevelIsOne()
    {
        $GLOBALS['TCA']['testtable'] = [
            'ctrl' => [
                'rootLevel' => 1,
            ],
            'columns' => [],
        ];

        $subject = new CreateRecordOperation(
            new RecordRepresentation(
                [],
                new RecordInstanceIdentifier('testtable', 'remoteId')
            )
        );

        self::assertEquals(0, $subject->getDataForDataHandler()['pid']);
    }
}
