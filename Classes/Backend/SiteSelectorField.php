<?php

namespace ApacheSolrForTypo3\Solr\Backend;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 - Thomas Hohn <tho@systime.dk>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
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
     * @param string $selectorName Name to be used in the select's name attribute
     * @param Site $selectedSite Optional, currently selected site
     * @return string Site selector HTML code
     */
    public function getAvailableSitesSelector(
        $selectorName,
        Site $selectedSite = null
    ) {
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
