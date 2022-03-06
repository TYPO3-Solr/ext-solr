<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ApacheSolrForTypo3\Solr\System\Cache;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Provides a two level cache that uses an in memory cache as the first level cache and
 * the TYPO3 caching framework cache as the second level cache.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class TwoLevelCache
{
    /**
     * @var string
     */
    protected string $cacheName = '';

    /**
     * @var array
     */
    protected static array $firstLevelCache = [];

    /**
     * @var FrontendInterface
     */
    protected FrontendInterface $secondLevelCache;

    /**
     * @param string $cacheName
     * @param FrontendInterface|null $secondaryCacheFrontend
     * @throws NoSuchCacheException
     */
    public function __construct(string $cacheName, FrontendInterface $secondaryCacheFrontend = null)
    {
        $this->cacheName = $cacheName;
        if ($secondaryCacheFrontend == null) {
            /* @var CacheManager $cacheManager */
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            $this->secondLevelCache = $cacheManager->getCache($cacheName);
        } else {
            $this->secondLevelCache = $secondaryCacheFrontend;
        }
    }

    /**
     * Retrieves a value from the first level cache.
     *
     * @param string $cacheId
     * @return mixed|null
     */
    protected function getFromFirstLevelCache(string $cacheId)
    {
        if (!empty(self::$firstLevelCache[$this->cacheName][$cacheId])) {
            return self::$firstLevelCache[$this->cacheName][$cacheId];
        }

        return null;
    }

    /**
     * Write a value to the first level cache.
     *
     * @param string $cacheId
     * @param mixed $value
     */
    protected function setToFirstLevelCache(string $cacheId, $value): void
    {
        self::$firstLevelCache[$this->cacheName][$cacheId] = $value;
    }

    /**
     * Retrieves a value from the first level cache if present and
     * from the second level if not.
     *
     * @param string $cacheId
     * @return mixed
     */
    public function get(string $cacheId)
    {
        $cacheId = $this->sanitizeCacheId($cacheId);

        $firstLevelResult = $this->getFromFirstLevelCache($cacheId);
        if ($firstLevelResult !== null) {
            return $firstLevelResult;
        }

        $secondLevelResult = $this->secondLevelCache->get($cacheId);
        $this->setToFirstLevelCache($cacheId, $secondLevelResult);

        return $secondLevelResult;
    }

    /**
     * Write a value to the first and second level cache.
     *
     * @param string $cacheId
     * @param mixed $value
     */
    public function set(string $cacheId, $value): void
    {
        $cacheId = $this->sanitizeCacheId($cacheId);

        $this->setToFirstLevelCache($cacheId, $value);
        $this->secondLevelCache->set($cacheId, $value);
    }

    /**
     * Flushes the cache
     */
    public function flush(): void
    {
        self::$firstLevelCache[$this->cacheName] = [];
        $this->secondLevelCache->flush();
    }

    /**
     * Sanitizes the cache id to ensure compatibility with the FrontendInterface::PATTERN_ENTRYIDENTIFIER
     *
     * @see \TYPO3\CMS\Core\Cache\Frontend\AbstractFrontend::isValidEntryIdentifier()
     *
     * @param string $cacheId
     * @return string
     */
    protected function sanitizeCacheId(string $cacheId): string
    {
        return preg_replace('/[^a-zA-Z0-9_%\\-&]/', '-', $cacheId);
    }
}
