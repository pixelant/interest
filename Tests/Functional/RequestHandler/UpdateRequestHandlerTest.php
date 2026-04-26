<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Interest\Tests\Functional\RequestHandler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class UpdateRequestHandlerTest extends AbstractRecordRequestHandlerTestCase
{
    use UpdateRequestTestTrait;

    private const REQUEST_METHOD = 'PUT';
}
