<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\Hook;

use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Hook\ProcessCmdmap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ProcessCmdmapTest extends UnitTestCase
{
    /**
     * @test
     */
    public function deletesRemoteIdIfOwnerRecordHasBeenDeleted()
    {
        $this->resetSingletonInstances = true;

        $dataHandlerMock = $this->createMock(DataHandler::class);

        $dataHandlerMock
            ->expects(self::exactly(1))
            ->method('hasDeletedRecord')
            ->with('table', 1)
            ->willReturn(true);

        $mappingRepositoryMock = $this->createMock(RemoteIdMappingRepository::class);

        $mappingRepositoryMock
            ->expects(self::exactly(1))
            ->method('remove')
            ->with('RemoteId');

        $mappingRepositoryMock
            ->expects(self::exactly(1))
            ->method('getRemoteId')
            ->with('table', 1)
            ->willReturn('RemoteId');

        GeneralUtility::setSingletonInstance(RemoteIdMappingRepository::class, $mappingRepositoryMock);

        $subject = new ProcessCmdmap();

        $subject->processCmdmap_postProcess(
            'delete',
            'table',
            1,
            null,
            $dataHandlerMock,
            null,
            null
        );
    }

    /**
     * @test
     */
    public function keepsRemoteIdIfOwnerRecordHasNotBeenDeleted()
    {
        $this->resetSingletonInstances = true;

        $dataHandlerMock = $this->createMock(DataHandler::class);

        $dataHandlerMock
            ->expects(self::exactly(1))
            ->method('hasDeletedRecord')
            ->with('table', 1)
            ->willReturn(false);

        $mappingRepositoryMock = $this->createMock(RemoteIdMappingRepository::class);

        $mappingRepositoryMock
            ->expects(self::never())
            ->method('remove');

        $mappingRepositoryMock
            ->expects(self::never())
            ->method('getRemoteId')
            ->with('table', 1);

        GeneralUtility::setSingletonInstance(RemoteIdMappingRepository::class, $mappingRepositoryMock);

        $subject = new ProcessCmdmap();

        $subject->processCmdmap_postProcess(
            'delete',
            'table',
            1,
            null,
            $dataHandlerMock,
            null,
            null
        );
    }
}
