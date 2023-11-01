<?php

declare(strict_types=1);

namespace Pixelant\Interest\Authentication;

use Psr\Http\Message\ServerRequestInterface;

class HttpBackendUserAuthenticationForTypo3v12 extends AbstractHttpBackendUserAuthentication
{
    /**
     * Fetches login credentials from basic HTTP authentication header.
     *
     * @param ServerRequestInterface $request
     * @return array
     * @phpstan-ignore-next-line
     */
    public function getLoginFormData(ServerRequestInterface $request)
    {
        return $this->internalGetLoginFormData($request);
    }
}
