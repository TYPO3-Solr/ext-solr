<?php
namespace ApacheSolrForTypo3\Solr\Backend\SolrModule;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Thomas Hohn <tho@systime.dk>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Search Statistics Module
 *
 * @author Thomas Hohn <tho@systime.d>
 */
class SearchStatisticsModuleController extends AbstractModuleController
{
    /**
     * Module name, used to identify a module f.e. in URL parameters.
     *
     * @var string
     */
    protected $moduleName = 'SearchStatistics';

    /**
     * Module title, shows up in the module menu.
     *
     * @var string
     */
    protected $moduleTitle = 'Search Statistics';

    /**
     * Index action, shows an overview of the state of the Solr index
     *
     * @return void
     */
    public function indexAction()
    {
        $siteRootPageId = $this->site->getRootPageId();
        $statisticsRepository = GeneralUtility::makeInstance(StatisticsRepository::class);

        // @TODO: Do we want Typoscript constants to restrict the results?
        $this->view->assign(
            'top_search_phrases',
            $statisticsRepository->getTopKeyWordsWithHits($siteRootPageId, 30, 5)
        );
        $this->view->assign(
            'top_search_phrases_without_hits',
            $statisticsRepository->getTopKeyWordsWithoutHits($siteRootPageId, 30, 5)
        );
        $this->view->assign(
            'search_phrases_statistics',
            $statisticsRepository->getSearchStatistics($siteRootPageId, 30, 100)
        );

        $labels = ["January", "February", "March", "April", "May", "June", "July"];
        $data = [65, 59, 70, 81, 56, 55, 40];
        $this->view->assign('queriesChartLabels', json_encode($labels));
        $this->view->assign('queriesChartData', json_encode($data));
    }
}
