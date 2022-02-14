<?php

declare(strict_types=1);


namespace Pixelant\Interest\RequestHandler;


use Pixelant\Interest\Authentication\HttpBackendUserAuthentication;
use Pixelant\Interest\Domain\Repository\TokenRepository;
use Pixelant\Interest\RequestHandler\Exception\InvalidArgumentException;
use Pixelant\Interest\RequestHandler\Exception\UnauthorizedAccessException;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Authentication\LoginType;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AuthenticateRequestHandler extends AbstractRequestHandler
{

    /**
     * @inheritDoc
     */
    public function handle(): ResponseInterface
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            throw new UnauthorizedAccessException(
                'Authorization requires POST method.',
                $this->request
            );
        }

        $authorizationHeader = $this->request->getHeader('authorization')[0]
            ?? $this->request->getHeader('redirect_http_authorization')[0]
            ?? '';

        [$scheme, $authorizationData] = GeneralUtility::trimExplode(' ', $authorizationHeader, true);

        if ($scheme === null) {
            throw new InvalidArgumentException(
                'No authorization scheme provided.',
                $this->request
            );
        }

        if (strtolower($scheme) !== 'basic') {
            throw new InvalidArgumentException(
                'Unknown authorization scheme "' . $scheme . '".',
                $this->request
            );
        }

        $authorizationData = base64_decode($authorizationData, true);

        if (strpos($authorizationData, ':') === false) {
            throw new InvalidArgumentException(
                'Authorization data couldn\'t be decoded. Missing ":" separating username and password.',
                $this->request
            );
        }

        [$username, $password] = explode(':', $authorizationData);

        /** @var HttpBackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];

        $backendUser->setLoginFormData([
            'status' => LoginType::LOGIN,
            'uname' => $username,
            'uident_text' => $password,
        ]);

        $backendUser->checkAuthentication();

        if (empty($backendUser->user['uid'])) {
            throw new UnauthorizedAccessException(
                'Basic login failed.',
                $this->request
            );
        }

        $token = GeneralUtility::makeInstance(TokenRepository::class)
            ->createTokenForBackendUser($backendUser->user[$backendUser->userid_column]);

        return GeneralUtility::makeInstance(
            JsonResponse::class,
            [
                'success' => true,
                'token' => $token,
            ],
            200
        );
    }
}
