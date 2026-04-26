<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Interest\Tests\Functional\RequestHandler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class CreateOrUpdateRequestHandlerTest extends AbstractRecordRequestHandlerTestCase
{
    use CreateRequestTestTrait;
    use UpdateRequestTestTrait;

    private const REQUEST_METHOD = 'PATCH';
}
