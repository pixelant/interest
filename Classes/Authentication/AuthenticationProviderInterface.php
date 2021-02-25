<?php
declare(strict_types=1);

namespace Pixelant\Interest\Authentication;

use Pixelant\Interest\Http\InterestRequestInterface;

interface AuthenticationProviderInterface
{
    /**
     * Tries to authenticate the current request
     *
     * @param InterestRequestInterface $request
     * @return bool Returns if the authentication was successful
     */
    public function authenticate(InterestRequestInterface $request): bool;
}
