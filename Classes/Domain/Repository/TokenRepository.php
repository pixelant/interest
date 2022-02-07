<?php

declare(strict_types=1);


namespace Pixelant\Interest\Domain\Repository;

/**
 * Repository for bearer tokens.
 */
class TokenRepository extends AbstractRepository
{
    public const TABLE_NAME = 'tx_interest_api_token';

    /**
     * Returns the UID for the backend user matching the token (or zero if no user was found or the token has expired).
     *
     * @param string $token
     * @return int
     */
    public function findBackendUserIdByToken(string $token): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return (int)$queryBuilder
            ->select('be_user')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('token', $queryBuilder->createNamedParameter($token)),
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq('expiry', 0),
                    $queryBuilder->expr()->lt('expiry', time())
                )
            )
            ->execute()
            ->fetchColumn();
    }
}
