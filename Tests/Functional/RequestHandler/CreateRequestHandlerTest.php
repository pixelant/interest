<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Interest\Tests\Functional\RequestHandler;

class CreateRequestHandlerTest extends AbstractRecordRequestHandlerTestCase
{
    use CreateRequestTestTrait;

    private const REQUEST_METHOD = 'POST';
}
