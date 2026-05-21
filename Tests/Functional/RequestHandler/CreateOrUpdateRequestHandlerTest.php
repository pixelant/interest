<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Interest\Tests\Functional\RequestHandler;

class CreateOrUpdateRequestHandlerTest extends AbstractRecordRequestHandlerTestCase
{
    use CreateRequestTestTrait;
    use UpdateRequestTestTrait;

    private const REQUEST_METHOD = 'PATCH';
}
