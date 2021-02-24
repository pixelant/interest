<?php
declare(strict_types=1);

namespace Pixelant\Interest\Controller;

use Pixelant\Interest\Http\InterestRequestInterface;

interface AccessControllerInterface
{
    /**
     * Returns if the current request's client has access to the requested resource
     *
     * @param InterestRequestInterface $request
     * @return bool
     */
    public function getAccess(InterestRequestInterface $request): bool;

    /**
     * Returns if the given request needs authentication
     *
     * @param InterestRequestInterface $request
     * @return bool
     */
    public function requestNeedsAuthentication(InterestRequestInterface $request): bool;
}
