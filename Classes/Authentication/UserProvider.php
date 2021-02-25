<?php
declare(strict_types=1);

namespace Pixelant\Interest\Authentication;

use Pixelant\Interest\ObjectManagerInterface;
use TYPO3\CMS\Core\Authentication\AuthenticationService;

class UserProvider implements UserProviderInterface
{

    const BE_USERS_TABLE = 'be_users';

    /**
     * @var AuthenticationService
     */
    protected AuthenticationService $autheticationService;

    /**
     * UserProvider constructor.
     * @param AuthenticationService $authenticationService
     */
    public function __construct(ObjectManagerInterface $objectManager, AuthenticationService $authenticationService)
    {
        $this->autheticationService = $authenticationService;
    }

    /**
     * @param string $username
     * @param string $password
     * @return bool
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function checkCredentials(string $username, string $password): bool
    {
        $user = [
            'uname' => $username,
            'password' => password_hash($password, PASSWORD_ARGON2I),
            'uident_text' => $password
        ];

        $this->autheticationService->initAuth('authUser', $user, ['db_user' => ['table' => self::BE_USERS_TABLE]], $GLOBALS['BE_USER']);
        $statusCode = $this->autheticationService->authUser($user);

        if ($statusCode === 200){
            return true;
        }

        return false;
    }
}
