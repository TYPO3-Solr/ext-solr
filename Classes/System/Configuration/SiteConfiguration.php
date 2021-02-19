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

use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class represents the configuration from the site configuration
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class SiteConfiguration implements UnifyConfigurationInterface
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
     * @var Site
     */
    protected $site = null;

    /**
     * The unified configuration
     *
     * @var array
     */
    protected $configuration = [];

    protected $renameConfigurationName = [
        'solr_use_write_connection' => 'solr_enabled_write'
    ];

    protected $booleanValues = [
        'solr_enabled_read',
        'solr_enabled_write',
        'solr_use_write_connection'
    ];

    /**
     * This constructor contains all required parameters used for other
     *
     * @param int $pageUid
     * @param int $languageUid
     */
    public function __construct(int $pageUid, int $languageUid = 0)
    {
        $this->rootPageUid = $pageUid;
        $this->languageUid = $languageUid;
    }

    /**
     * Return an instance of site configuration.
     *
     * @param Site $site
     * @param int $languageUid
     * @return static
     */
    public static function newWithSite(Site $site, int $languageUid = 0): self
    {
        $siteConfiguration = new self(
            $site->getRootPageId(),
            $languageUid
        );
        $siteConfiguration->site = $site;

        return $siteConfiguration;
    }

    /**
     * {@inheritDoc}
     */
    public function load(): UnifyConfigurationInterface
    {
        $site = $this->getSite();
        if (!($site instanceof Site)) {
            return $this;
        }

        $this->addSiteConfigurationToArray($site->getConfiguration())
            ->addSiteConfigurationToArray($site->getLanguageById($this->languageUid)->toArray());

        return $this;
    }

    /**
     * Add the site configuration
     *
     * @param array $configuration
     * @return $this
     */
    protected function addSiteConfigurationToArray(array $configuration): SiteConfiguration
    {
        foreach ($configuration as $name => $value) {
            if (substr($name, 0, 5) !== 'solr_') {
                continue;
            }

            if (in_array($name, $this->booleanValues)) {
                $this->configuration[$name] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } else {
                $this->configuration[$name] = $value;
            }
        }
        return $this;
    }

    /**
     * Load the site configuration
     *
     * @return Site|null
     */
    protected function getSite(): ?Site
    {
        if ($this->site instanceof Site) {
            return $this->site;
        }

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            /* @var SiteFinder $siteFinder */
            $this->site = $siteFinder->getSiteByPageId($this->rootPageUid);
        } catch (SiteNotFoundException $e) {
            return null;
        }

        return $this->site;
    }

    /**
     * {@inheritDoc}
     */
    public function getUnifiedArray(): array
    {
        $result = [
            'connection' => [
                'read' => [],
                'write' => [],
            ]
        ];

        foreach ($this->configuration as $key => $value) {
            if (isset($this->renameConfigurationName[$key])) {
                $key = $this->renameConfigurationName[$key];
            }
            [$prefix, $key, $endpointKey] = explode('_', $key);
            $result['connection'][$endpointKey][$key] = $value;
        }

        return $result;
    }
}
