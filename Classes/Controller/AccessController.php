<?php

declare(strict_types=1);

namespace Pixelant\Interest\Controller;

use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\ObjectManagerInterface;

class AccessController implements AccessControllerInterface
{
    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * AccessController constructor.
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function getAccess(InterestRequestInterface $request): bool
    {
        return $this->objectManager->getAuthenticationProvider()->authenticate($request);
    }
}
