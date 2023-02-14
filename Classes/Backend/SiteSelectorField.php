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

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SiteSelectorField
 *
 * Responsible for generating SiteSelectorField
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class SiteSelectorField
{
    /**
     * Creates a dropdown selector of available TYPO3 sites with Solr configured.
     *
     * @param string $selectorName Name to be used in the selects name attribute
     * @param Site|null $selectedSite Optional, currently selected site
     * @return string Site selector HTML code
     * @throws DBALDriverException
     * @throws Throwable
     */
    public function getAvailableSitesSelector(
        string $selectorName,
        Site $selectedSite = null
    ): string {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);

        $sites = $siteRepository->getAvailableSites();
        $selector = '<select name="' . htmlspecialchars($selectorName) . '" class="form-control">';

        foreach ($sites as $site) {
            $selectedAttribute = '';
            if ($selectedSite !== null && $site->getRootPageId() === $selectedSite->getRootPageId()) {
                $selectedAttribute = ' selected="selected"';
            }

            $selector .= '<option value="' . htmlspecialchars($site->getRootPageId()) . '"' . $selectedAttribute . '>'
                . htmlspecialchars($site->getLabel())
                . '</option>';
        }

        $selector .= '</select>';

        return $selector;
    }
}
