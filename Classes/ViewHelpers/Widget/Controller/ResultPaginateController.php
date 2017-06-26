<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Widget\Controller;

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

use ApacheSolrForTypo3\Solr\Widget\AbstractWidgetController;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Class ResultPaginateController
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\ViewHelpers\Widget\Controller
 */
class ResultPaginateController extends AbstractWidgetController
{

    /**
     * @var array
     */
    protected $configuration = ['insertAbove' => true, 'insertBelow' => true, 'maximumNumberOfLinks' => 10, 'addQueryStringMethod' => ''];

    /**
     * @var \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet
     */
    protected $resultSet;

    /**
     * @var int
     */
    protected $currentPage = 1;

    /**
     * @var int
     */
    protected $displayRangeStart;

    /**
     * @var int
     */
    protected $displayRangeEnd;

    /**
     * @var int
     */
    protected $maximumNumberOfLinks = 99;

    /**
     * @var int
     */
    protected $numberOfPages = 1;

    /**
     * @return void
     */
    public function initializeAction()
    {
        $this->resultSet = $this->widgetConfiguration['resultSet'];

        ArrayUtility::mergeRecursiveWithOverrule($this->configuration, $this->widgetConfiguration['configuration'], false);
        $this->configuration['itemsPerPage'] = $this->getItemsPerPage();
        $this->numberOfPages = (int)ceil($this->resultSet->getUsedSearch()->getNumberOfResults() / $this->configuration['itemsPerPage']);
        $this->maximumNumberOfLinks = (int)$this->configuration['maximumNumberOfLinks'];
    }

    /**
     * Determines the number of results per page. When nothing is configured 10 will be returned.
     *
     * @return int
     */
    protected function getItemsPerPage()
    {
        $perPage = (int)$this->resultSet->getUsedSearch()->getQuery()->getResultsPerPage();
        return $perPage > 0 ? $perPage : 10;
    }

    /**
     * @param \ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext $controllerContext
     * @return \ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext
     */
    protected function setActiveSearchResultSet($controllerContext) {
        $controllerContext->setSearchResultSet($this->resultSet);
        return $controllerContext;
    }

    /**
     * @return void
     */
    public function indexAction()
    {
        // set current page
        $this->currentPage = $this->resultSet->getUsedPage() + 1;
        if ($this->currentPage < 1) {
            $this->currentPage = 1;
        }
        $this->view->assign('contentArguments', [$this->widgetConfiguration['as'] => $this->getDocuments(), 'pagination' => $this->buildPagination()]);
        $this->view->assign('configuration', $this->configuration);
        $this->view->assign('resultSet', $this->resultSet);
    }

    /**
     * If a certain number of links should be displayed, adjust before and after
     * amounts accordingly.
     *
     * @return void
     */
    protected function calculateDisplayRange()
    {
        $maximumNumberOfLinks = $this->maximumNumberOfLinks;
        if ($maximumNumberOfLinks > $this->numberOfPages) {
            $maximumNumberOfLinks = $this->numberOfPages;
        }
        $delta = floor($maximumNumberOfLinks / 2);
        $this->displayRangeStart = $this->currentPage - $delta;
        $this->displayRangeEnd = $this->currentPage + $delta - ($maximumNumberOfLinks % 2 === 0 ? 1 : 0);
        if ($this->displayRangeStart < 1) {
            $this->displayRangeEnd -= $this->displayRangeStart - 1;
        }
        if ($this->displayRangeEnd > $this->numberOfPages) {
            $this->displayRangeStart -= $this->displayRangeEnd - $this->numberOfPages;
        }
        $this->displayRangeStart = (int)max($this->displayRangeStart, 1);
        $this->displayRangeEnd = (int)min($this->displayRangeEnd, $this->numberOfPages);
    }

    /**
     * Returns an array with the keys "pages", "current", "numberOfPages", "nextPage" & "previousPage"
     *
     * @return array
     */
    protected function buildPagination()
    {
        $this->calculateDisplayRange();
        $pages = [];
        for ($i = $this->displayRangeStart; $i <= $this->displayRangeEnd; $i++) {
            $pages[] = ['number' => $i, 'isCurrent' => $i === $this->currentPage];
        }
        $pagination = ['pages' => $pages, 'current' => $this->currentPage, 'numberOfPages' => $this->numberOfPages, 'displayRangeStart' => $this->displayRangeStart, 'displayRangeEnd' => $this->displayRangeEnd, 'hasLessPages' => $this->displayRangeStart > 2, 'hasMorePages' => $this->displayRangeEnd + 1 < $this->numberOfPages];
        if ($this->currentPage < $this->numberOfPages) {
            $pagination['nextPage'] = $this->currentPage + 1;
        }
        if ($this->currentPage > 1) {
            $pagination['previousPage'] = $this->currentPage - 1;
        }
        return $pagination;
    }

    /**
     * @return \Apache_Solr_Document[]
     */
    protected function getDocuments()
    {
        $extbaseFrameworkConfiguration = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
        if (!empty($extbaseFrameworkConfiguration['features']['useRawDocuments'])) {
            return $this->resultSet->getUsedSearch()->getResultDocumentsRaw();
        } else {
            return $this->resultSet->getUsedSearch()->getResultDocumentsEscaped();
        }
    }
}
