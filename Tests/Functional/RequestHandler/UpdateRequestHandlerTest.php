<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Interest\Tests\Functional\RequestHandler;

class UpdateRequestHandlerTest extends AbstractRecordRequestHandlerTestCase
{
    use UpdateRequestTestTrait;

    private const REQUEST_METHOD = 'PUT';
}
