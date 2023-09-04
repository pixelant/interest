<?php

declare(strict_types=1);

namespace Pixelant\Interest\Authentication;

use Psr\Http\Message\ServerRequestInterface;

class HttpBackendUserAuthenticationForTypo3v11 extends AbstractHttpBackendUserAuthentication
{
    /**
     * Fetches login credentials from basic HTTP authentication header.
     *
     * @return array
     */
    public function getLoginFormData()
    {
        return $this->internalGetLoginFormData($GLOBALS['TYPO3_REQUEST']);
    }
}
