<?php

declare(strict_types=1);

namespace Pixelant\Interest\Domain\Model\Dto;

use Pixelant\Interest\DataHandling\Operation\Exception\ConflictException;
use Pixelant\Interest\Domain\Model\Dto\Exception\InvalidArgumentException;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Utility\TcaUtility;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * DTO to handle record instance identifier.
 */
class RecordInstanceIdentifier
{
    public const LANGUAGE_ASPECT_PREFIX = '|||L';

    /**
     * @var string
     */
    protected string $table;

    /**
     * The original remote id from construct.
     *
     * @var string
     */
    protected string $remoteId;

    /**
     * Language to use for processing.
     *
     * @var SiteLanguage|null
     */
    protected ?SiteLanguage $language;

    /**
     * @var string|null
     */
    protected ?string $workspace;

    /**
     * @var int|null
     */
    protected ?int $uid = null;

    /**
     * @param string $table
     * @param string $remoteId
     * @param string $language as RFC 1766/3066 string, e.g. nb or sv-SE.
     * @param string $workspace workspace represented with a remote ID.
     */
    public function __construct(
        string $table,
        string $remoteId,
        string $language = '',
        string $workspace = ''
    ) {
        $this->table = strtolower($table);
        $this->remoteId = $remoteId;
        $this->language = $this->resolveLanguage($language);
        $this->workspace = $workspace;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return SiteLanguage|null
     */
    public function getLanguage(): ?SiteLanguage
    {
        return $this->language;
    }

    /**
     * Returns true if a SiteLanguage is set.
     *
     * @return bool
     */
    public function hasLanguage(): bool
    {
        return $this->language !== null;
    }

    /**
     * Returns original unmodified remote id set in construct.
     *
     * @return string
     */
    public function getRemoteId(): string
    {
        return $this->remoteId;
    }

    /**
     * Returns workspace.
     *
     * @return string|null
     */
    public function getWorkspace(): ?string
    {
        return $this->workspace;
    }

    /**
     * Returns true if workspace is set.
     *
     * @return bool
     */
    public function hasWorkspace(): bool
    {
        return $this->workspace !== null;
    }

    /**
     * @return int
     */
    public function getUid(): int
    {
        if ($this->uid === null) {
            $this->uid = $this->resolveUid();
        }

        return $this->uid;
    }

    /**
     * @param int $uid
     */
    public function setUid(int $uid)
    {
        $this->uid = $uid;
    }

    /**
     * Returns remote id with aspects, such as language and workspace ID.
     * If language is null or language ID zero, the $remoteId is removed unchanged.
     *
     * @return string
     */
    public function getRemoteIdWithAspects(): string
    {
        if (
            !TcaUtility::isLocalizable($this->getTable())
            || $this->getLanguage() === null
            || $this->getLanguage()->getLanguageId() === 0
        ) {
            return $this->remoteId;
        }

        $languageAspect = self::LANGUAGE_ASPECT_PREFIX . $this->getLanguage()->getLanguageId();

        if (strpos($this->remoteId, $languageAspect) !== false) {
            return $this->remoteId;
        }

        $remoteId = $this->remoteId;

        return $remoteId . $languageAspect;
    }

    /**
     * @param string $remoteId
     * @return string
     */
    public function removeAspectsFromRemoteId(string $remoteId): string
    {
        if (strpos($remoteId, self::LANGUAGE_ASPECT_PREFIX) === false) {
            return $remoteId;
        }

        return substr($remoteId, 0, strpos($remoteId, self::LANGUAGE_ASPECT_PREFIX));
    }

    /**
     * Resolves a site language. If no language is defined, the sites's default language will be returned. If the
     * storagePid has no site, null will be returned.
     *
     * @param string|null $language
     * @return SiteLanguage|null
     * @throws InvalidArgumentException
     */
    protected function resolveLanguage(?string $language): ?SiteLanguage
    {
        if (!TcaUtility::isLocalizable($this->getTable()) || empty($language)) {
            return null;
        }

        /** @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        $sites = $siteFinder->getAllSites();

        $siteLanguages = [];

        foreach ($sites as $site) {
            $siteLanguages = array_merge($siteLanguages, $site->getAllLanguages());
        }

        // This is the equivalent of running array_unique, but supports objects.
        $siteLanguages = array_reduce($siteLanguages, function (array $uniqueSiteLanguages, SiteLanguage $item) {
            /** @var SiteLanguage $siteLanguage */
            foreach ($uniqueSiteLanguages as $siteLanguage) {
                if ($siteLanguage->getLanguageId() === $item->getLanguageId()) {
                    return $uniqueSiteLanguages;
                }
            }

            $uniqueSiteLanguages[] = $item;

            return $uniqueSiteLanguages;
        }, []);

        foreach ($siteLanguages as $siteLanguage) {
            $hreflang = $siteLanguage->getHreflang();

            // In case this is the short form, e.g. "nb" or "sv", not "nb-NO" or "sv-SE".
            if (strlen($language) === 2) {
                $hreflang = substr($hreflang, 0, 2);
            }

            if (strtolower($hreflang) === strtolower($language)) {
                return $siteLanguage;
            }
        }

        throw new InvalidArgumentException(
            'The language "' . $language . '" is not defined in this TYPO3 instance.'
        );
    }

    /**
     * Resolves the UID for the remote ID.
     *
     * @return int
     * @throws ConflictException
     */
    protected function resolveUid(): int
    {
        $mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        if (
            $mappingRepository->exists($this->getRemoteIdWithAspects())
            && $mappingRepository->table($this->getRemoteIdWithAspects()) !== $this->getTable()
        ) {
            throw new ConflictException(
                'The remote ID "' . $this->getRemoteIdWithAspects() . '" exists, '
                . 'but doesn\'t belong to the table "' . $this->getRemoteIdWithAspects() . '".',
                1634213051764
            );
        }

        return $mappingRepository->get($this->getRemoteIdWithAspects());
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getRemoteIdWithAspects();
    }
}
