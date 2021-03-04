<?php
declare(strict_types=1);

namespace Pixelant\Interest\Authentication;

use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UserProvider implements UserProviderInterface
{

    /**
     * Compare given username and password with current BE user credentials.
     *
     * @param string $username
     * @param string $password
     * @return bool
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     * @throws \TYPO3\CMS\Core\Crypto\PasswordHashing\InvalidPasswordHashException
     */
    public function checkCredentials(string $username, string $password): bool
    {
        $saltFactory = GeneralUtility::makeInstance(PasswordHashFactory::class);
        $hashedPassword = $saltFactory->get($GLOBALS['BE_USER']->user['password'], $GLOBALS['BE_USER']->loginType);
        $isMatchedPasswords = $hashedPassword->checkPassword($password, $GLOBALS['BE_USER']->user['password']);
        $isMatchedUsernames = $username === $GLOBALS['BE_USER']->user['username'];

        if ($isMatchedPasswords && $isMatchedUsernames){
            return true;
        }

        return false;
    }
}
