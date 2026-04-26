<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Interest\Tests\Functional\RequestHandler;

use FriendsOfTYPO3\Interest\Domain\Repository\RemoteIdMappingRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class CreateRequestHandlerTest extends AbstractRecordRequestHandlerTestCase
{
    use CreateRequestTestTrait;

    private const REQUEST_METHOD = 'POST';
}
