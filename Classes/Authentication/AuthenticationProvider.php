<?php
declare(strict_types=1);

namespace Pixelant\Interest\Authentication;

use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\ObjectManagerInterface;
use Psr\Http\Message\ResponseInterface;

class AuthenticationProvider extends AbstractAuthenticationProvider
{
    const TOKEN_TABLE = 'tx_interest_api_token';

    /**
     * @var UserProviderInterface
     */
    protected UserProviderInterface $userProvider;

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * AuthenticationProvider constructor.
     * @param UserProviderInterface $userProvider
     */
    public function __construct(UserProviderInterface $userProvider, ObjectManagerInterface $objectManager)
    {
        $this->userProvider = $userProvider;
        $this->objectManager = $objectManager;
    }

    /**
     * Token validation processing
     *
     * @param InterestRequestInterface $request
     * @return bool
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function authenticate(InterestRequestInterface $request): bool
    {
        $serverParameters = $request->getServerParams();
        $token = '';

        if (isset($serverParameters["HTTP_AUTHORIZATION"])){
            $token = $serverParameters["HTTP_AUTHORIZATION"];
        }else if(isset($serverParameters["REDIRECT_HTTP_AUTHORIZATION"])){
            $token = $serverParameters["REDIRECT_HTTP_AUTHORIZATION"];
        }

        $queryBuilder = $this->objectManager->getQueryBuilder(self::TOKEN_TABLE);
        $matchedTokens = $queryBuilder
            ->select('token','expires_in')
            ->from(self::TOKEN_TABLE)
            ->where(
                $queryBuilder->expr()->eq('token', "'".$token."'")
            )
            ->execute()
            ->fetchAllAssociative();

        if (empty($matchedTokens)){
            return false;
        }

        $tokenData = reset($matchedTokens);

        if (time() > $tokenData['expires_in'] && $tokenData['expires_in'] !== 0){
            $queryBuilder
                ->delete(self::TOKEN_TABLE)
                ->where(
                    $queryBuilder->expr()->eq('token', "'".$tokenData['token']."'")
                )
                ->execute();

            return false;
        }

        return true;
    }
}
