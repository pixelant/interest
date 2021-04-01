<?php

declare(strict_types=1);

namespace Pixelant\Interest\Cache;

use TYPO3\CMS\Core\Cache\Exception;
use TYPO3\CMS\Core\Cache\Exception\InvalidDataException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Typo3DatabaseBackend extends \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend
{

    /**
     * @param FrontendInterface $cache
     */
    public function setCache(FrontendInterface $cache)
    {
        parent::setCache($cache);
        $this->cacheTable = 'tx_interest_api_token';
    }

    /**
     * Saves data in a cache file.
     *
     * @param string $entryIdentifier An identifier for this specific cache entry
     * @param string $data The data to be stored
     * @param array $tags Tags to associate with this cache entry
     * @param int $lifetime Lifetime of this cache entry in seconds. If NULL is specified, the default lifetime is used. "0" means unlimited lifetime.
     * @throws Exception if no cache frontend has been set.
     * @throws InvalidDataException if the data to be stored is not a string.
     */
    public function set($entryIdentifier, $data, array $tags = [], $lifetime = null)
    {
        $this->throwExceptionIfFrontendDoesNotExist();
        if (!is_string($data)) {
            throw new InvalidDataException(
                'The specified data is of type "' . gettype($data) . '" but a string is expected.',
                1236518298
            );
        }
        if ($lifetime === null) {
            $lifetime = $this->defaultLifetime;
        }
        if ($lifetime === 0 || $lifetime > $this->maximumLifetime) {
            $lifetime = $this->maximumLifetime;
        }
        $expires = $GLOBALS['EXEC_TIME'] + $lifetime;
        if ($this->compression) {
            $data = gzcompress($data, $this->compressionLevel);
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($this->cacheTable);

        $queryBuilder
            ->update($this->cacheTable)
            ->where(
                $queryBuilder->expr()->eq('token', $queryBuilder->createNamedParameter($entryIdentifier))
            )
            ->set('cached_data', $data)
            ->execute();

        if (!empty($tags)) {
            $tagRows = [];
            foreach ($tags as $tag) {
                $tagRows[] = [$entryIdentifier, $tag];
            }
            GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable($this->tagsTable)
                ->bulkInsert($this->tagsTable, $tagRows, ['identifier', 'tag']);
        }
    }
}
