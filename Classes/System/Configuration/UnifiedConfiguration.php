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

namespace ApacheSolrForTypo3\Solr\System\Configuration;

use ApacheSolrForTypo3\Solr\System\Util\ArrayAccessor;

/**
 * This class wraps all configuration information and offers one interface
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class UnifiedConfiguration extends ArrayAccessor
{
    /**
     * The root page ID
     *
     * @var int
     */
    protected $rootPageUid = 0;

    /**
     * The language uid
     *
     * @var int
     */
    protected $languageUid = 0;

    /**
     * This constructor contains all required parameters used for other
     *
     * @param int $pageUid
     * @param int $languageUid
     */
    public function __construct(int $pageUid, int $languageUid = 0)
    {
        parent::__construct([], '.');
        $this->rootPageUid = $pageUid;
        $this->languageUid = $languageUid;
    }

    /**
     * Merge another configuration into this one
     *
     * @param UnifyConfigurationInterface $configuration
     * @return $this
     */
    public function mergeConfigurationByObject(UnifyConfigurationInterface $configuration): UnifiedConfiguration
    {
        $data = $configuration->load()->getUnifiedArray();
        $this->mergeArray($data);

        return $this;
    }

    /**
     * Check if the given path contains a true value
     *
     * @param string $path
     * @param bool $fallback
     * @return bool
     */
    public function isTrue(string $path, bool $fallback = false): bool
    {
        $value = $this->get($path);
        if ($value === null) {
            return $fallback;
        }

        return (bool)$value;
    }

    /**
     * Returns an integer by given configuration path.
     *
     * @param string $path
     * @param int $fallback
     * @return int
     */
    public function getInteger(string $path, int $fallback = 0): int
    {
        $value = $this->get($path);
        if ($value === null) {
            return $fallback;
        }

        return (int)$value;
    }
}
