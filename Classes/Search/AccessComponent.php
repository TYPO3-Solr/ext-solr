<?php
namespace ApacheSolrForTypo3\Solr\Search;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Query;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Access search component
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class AccessComponent extends AbstractComponent implements QueryAware
{

    /**
     * Solr query
     *
     * @var Query
     */
    protected $query;

    /**
     * @var SiteHashService
     */
    protected $siteHashService;

    /**
     * AccessComponent constructor.
     * @param SiteHashService|null $siteService
     */
    public function __construct(SiteHashService $siteService = null)
    {
        $this->siteHashService = is_null($siteService) ? GeneralUtility::makeInstance(SiteHashService::class) : $siteService;
    }

    /**
     * Initializes the search component.
     */
    public function initializeSearchComponent()
    {
        $allowedSites = $this->siteHashService->getAllowedSitesForPageIdAndAllowedSitesConfiguration(
            $GLOBALS['TSFE']->id,
            $this->searchConfiguration['query.']['allowedSites']
        );

        $this->query->setSiteHashFilter($allowedSites);
        $this->query->setUserAccessGroups(explode(',', $GLOBALS['TSFE']->gr_list));
    }

    /**
     * Provides the extension component with an instance of the current query.
     *
     * @param Query $query Current query
     */
    public function setQuery(Query $query)
    {
        $this->query = $query;
    }
}
