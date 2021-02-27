<?php

namespace Pixelant\Interest\Handler;

use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\ObjectManager;
use Pixelant\Interest\ObjectManagerInterface;
use Pixelant\Interest\Router\Route;
use Pixelant\Interest\Router\RouterInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AuthenticationHandler implements HandlerInterface
{

    const TOKEN_TABLE = 'tx_interest_api_token';

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function authenticate(InterestRequestInterface $request): ResponseInterface
    {
        $username = null;
        $password = null;
        $objectManager = $this->getObjectManager();
        $userProvider = $objectManager->getUserProvider();
        $responseFactory = $objectManager->getResponseFactory();

        if (isset($_SERVER['HTTP_AUTHORIZATION'])){
            if (str_starts_with(strtolower($_SERVER['HTTP_AUTHORIZATION']), 'basic')) {
                list($username, $password) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            }
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            if (str_starts_with(strtolower($_SERVER['REDIRECT_HTTP_AUTHORIZATION']), 'basic')) {
                list($username, $password) = explode(
                    ':',
                    base64_decode(substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 6))
                );
            }
        }

        if ($userProvider->checkCredentials($username, $password)) {
              $data = $this->getTokenOrCreateNew($objectManager);
              return $responseFactory->createSuccessResponse($data, 200, $request);
        } else {
            return $responseFactory->createErrorResponse([], 401, $request);
        }
    }

    /**
     * @return ObjectManagerInterface
     */
    public function getObjectManager(): ObjectManagerInterface
    {
        return GeneralUtility::makeInstance(ObjectManager::class);
    }

    /**
     * @param ObjectManagerInterface $objectManager
     * @param string $token
     * @return array
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function getTokenOrCreateNew(ObjectManagerInterface $objectManager, string $token = ''): array
    {
        $queryBuilder = $objectManager->getQueryBuilder(self::TOKEN_TABLE);

        if ($token === ''){
            $token = $objectManager->get(Random::class)->generateRandomHexString(20);
        }

        $existingToken = $queryBuilder
            ->select('token', 'expires_in')
            ->from(self::TOKEN_TABLE)
            ->where(
                $queryBuilder->expr()->eq('token', "'".$token."'")
            )
            ->execute()
            ->fetchAllAssociative();

        if (empty($existingToken)){
            $expiresIn = time()+3600;
            $queryBuilder
                ->insert(self::TOKEN_TABLE)
                ->values([
                    'token' => $token,
                    'expires_in' => $expiresIn
                ])
                ->execute();

            return $this->getTokenOrCreateNew($objectManager, $token);
        }

        return $existingToken;

    }
    public function configureRoutes(RouterInterface $router, InterestRequestInterface $request)
    {
        $resourceType = $request->getResourceType()->__toString();
        $router->add(Route::post($resourceType, [$this, 'authenticate']));
    }
}
