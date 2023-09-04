<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler;

use Pixelant\Interest\Domain\Repository\TokenRepository;
use Pixelant\Interest\RequestHandler\Exception\InvalidArgumentException;
use Pixelant\Interest\RequestHandler\Exception\UnauthorizedAccessException;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AuthenticateRequestHandler extends AbstractRequestHandler
{
    /**
     * @return ResponseInterface
     * @throws UnauthorizedAccessException
     * @throws InvalidArgumentException
     */
    public function handle(): ResponseInterface
    {
        if (!$GLOBALS['BE_USER']->isAuthenticated()) {
            throw new UnauthorizedAccessException(
                'Basic login failed.',
                $this->request
            );
        }

        $token = GeneralUtility::makeInstance(TokenRepository::class)
            ->createTokenForBackendUser($GLOBALS['BE_USER']->getUserId());

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
