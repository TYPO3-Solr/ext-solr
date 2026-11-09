<?php

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

namespace ApacheSolrForTypo3\Solr\Backend;

use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SiteSelectorField
 *
 * Responsible for generating SiteSelectorField
 */
class SiteSelectorField
{
    /**
     * Creates a dropdown selector of available TYPO3 sites.
     *
     * Uses SiteFinder::getAllSites() instead of SiteRepository::getAvailableSites()
     * to avoid initializing a full TSFE (including frontend TypoScript AST) for
     * every site. The dropdown only needs root page IDs and labels, not Solr
     * configuration. Validation of Solr-enabled sites happens on save via
     * validateAdditionalFields().
     *
     * @param string $selectorName Name to be used in the selects name attribute
     * @param Site|null $selectedSite Optional, currently selected site
     *
     * @return string Site selector HTML code
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getAvailableSitesSelector(
        string $selectorName,
        ?Site $selectedSite = null,
    ): string {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $selectedRootPageId = $selectedSite?->getRootPageId();

        $selector = '<select name="' . htmlspecialchars($selectorName) . '" class="form-control">';

        foreach ($siteFinder->getAllSites() as $site) {
            $rootPageId = $site->getRootPageId();
            $selectedAttribute = '';
            if ($selectedRootPageId !== null && $rootPageId === $selectedRootPageId) {
                $selectedAttribute = ' selected="selected"';
            }

            $selector .= '<option value="' . htmlspecialchars((string)$rootPageId) . '"' . $selectedAttribute . '>'
                . htmlspecialchars($site->getIdentifier())
                . '</option>';
        }

        $selector .= '</select>';

        return $selector;
    }
}
