<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Interest\Authentication;

use FriendsOfTYPO3\Interest\Domain\Repository\TokenRepository;
use FriendsOfTYPO3\Interest\RequestHandler\Exception\InvalidArgumentException;
use FriendsOfTYPO3\Interest\RequestHandler\Exception\UnauthorizedAccessException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class HttpBackendUserAuthentication extends BackendUserAuthentication
{
    /**
     * Check if user is authenticated.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->getUserId() !== 0 && $this->getUserId() !== null;
    }

    /**
     * Returns the user's UID.
     *
     * @return int
     */
    public function getUserId(): int
    {
        return $this->user['uid'] ?? 0;
    }

    public function checkAuthentication(ServerRequestInterface $request): void
    {
        $this->authenticateBearerToken($request);

        if ($this->isAuthenticated()) {
            return;
        }

        parent::checkAuthentication($request);
    }

    /**
     * Fetches login credentials from basic HTTP authentication header.
     *
     * @param ServerRequestInterface $request
     * @return array
     * @throws UnauthorizedAccessException
     * @throws InvalidArgumentException
     */
    public function getLoginFormData(ServerRequestInterface $request)
    {
        if (strtolower($request->getMethod()) !== 'post') {
            throw new UnauthorizedAccessException(
                'Authorization requires POST method.',
                $request
            );
        }

        $authorizationHeader = $request->getHeader('authorization')[0]
            ?? $request->getHeader('redirect_http_authorization')[0]
            ?? '';

        [$scheme, $authorizationData] = GeneralUtility::trimExplode(' ', $authorizationHeader, true);

        if ($scheme === null) {
            throw new InvalidArgumentException(
                'No authorization scheme provided.',
                $request
            );
        }

        if (strtolower($scheme) !== 'basic') {
            throw new InvalidArgumentException(
                'Unknown authorization scheme "' . $scheme . '".',
                $request
            );
        }

        $authorizationData = base64_decode($authorizationData, true);

        if (!str_contains($authorizationData, ':')) {
            throw new InvalidArgumentException(
                'Authorization data couldn\'t be decoded. Missing ":" separating username and password.',
                $request
            );
        }

        [$username, $password] = explode(':', $authorizationData);

        $loginData = [
            'status' => 'login',
            'uname'  => $username,
            'uident' => $password,
        ];

        return $this->processLoginData($loginData, $request);
    }

    /**
     * Authenticates a token provided in the request.
     *
     * @param ServerRequestInterface $request
     * @throws UnauthorizedAccessException
     */
    protected function authenticateBearerToken(ServerRequestInterface $request): void
    {
        $authorizationHeader = $request->getHeader('authorization')[0]
            ?? $request->getHeader('redirect_http_authorization')[0]
            ?? '';

        [$scheme, $token] = GeneralUtility::trimExplode(' ', $authorizationHeader, true);

        if ($scheme === null) {
            return;
        }

        if (is_string($scheme) && strtolower($scheme) !== 'bearer') {
            return;
        }

        $backendUserId = GeneralUtility::makeInstance(TokenRepository::class)
            ->findBackendUserIdByToken($token);

        if ($backendUserId === 0) {
            throw new UnauthorizedAccessException(
                'Invalid or expired bearer token.',
                $request
            );
        }

        $this->setBeUserByUid($backendUserId);

        $this->unpack_uc();

        $this->fetchGroupData();
        $this->backendSetUC();
    }

    /**
     * Returns the authentication service configuration with `BE_fetchUserIfNoSession` set to true.
     *
     * @return array
     */
    protected function getAuthServiceConfiguration(): array
    {
        $configuration = parent::getAuthServiceConfiguration();

        $configuration['BE_fetchUserIfNoSession'] = true;

        return $configuration;
    }
}
