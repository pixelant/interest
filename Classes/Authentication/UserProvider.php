<?php
declare(strict_types=1);

namespace Pixelant\Interest\Authentication;

use Pixelant\Interest\ObjectManagerInterface;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UserProvider implements UserProviderInterface
{

    const BE_USERS_TABLE = 'be_users';

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * UserProvider constructor.
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }
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
        $queryBuilder = $this->objectManager->getQueryBuilder(self::BE_USERS_TABLE);
        $saltFactory = $this->objectManager->get(PasswordHashFactory::class);

        $beUser = $queryBuilder
            ->select('*')
            ->from(self::BE_USERS_TABLE)
            ->where(
                $queryBuilder->expr()->eq('username', "'".$username."'")
            )
            ->execute()
            ->fetchAllAssociative();

        if (!$beUser){
            return false;
        }

        $beUser = $beUser[0];
        $hashedPassword = $saltFactory->get($beUser['password'], $GLOBALS['BE_USER']->loginType);
        $isMatching = $hashedPassword->checkPassword($password, $beUser['password']);

        if ($isMatching){
            return true;
        }

        return false;
    }
}
