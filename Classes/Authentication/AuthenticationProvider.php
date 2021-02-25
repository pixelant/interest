<?php
declare(strict_types=1);

namespace Pixelant\Interest\Authentication;

use Pixelant\Interest\Http\InterestRequestInterface;

class AuthenticationProvider extends AbstractAuthenticationProvider
{
    /**
     * @var UserProviderInterface
     */
    protected UserProviderInterface $userProvider;

    /**
     * AuthenticationProvider constructor.
     * @param UserProviderInterface $userProvider
     */
    public function __construct(UserProviderInterface $userProvider)
    {
        $this->userProvider = $userProvider;
    }
    /**
     * @param InterestRequestInterface $request
     * @return bool
     */
    public function authenticate(InterestRequestInterface $request): bool
    {
        $username = null;
        $password = null;

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

        return $this->userProvider->checkCredentials($username, $password);
    }
}
