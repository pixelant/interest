<?php

declare(strict_types=1);

namespace Pixelant\Interest\Authentication;

use Pixelant\Interest\Http\InterestRequestInterface;

abstract class AbstractAuthenticationProvider implements AuthenticationProviderInterface
{
    /**
     * Tries to authenticate the current request.
     *
     * @param InterestRequestInterface $request
     * @return bool
     */
    public function authenticate(InterestRequestInterface $request): bool
    {
        return false;
    }
}
