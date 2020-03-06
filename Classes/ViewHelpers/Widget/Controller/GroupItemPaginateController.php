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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;


/**
 * Class GroupItemPaginateController
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class GroupItemPaginateController extends AbstractPaginateWidgetController
{

    /**
     * @var SearchResultSet
     */
    protected $resultSet;

    /**
     * @var GroupItem
     */
    protected $groupItem;

    /**
     * @return void
     */
    public function initializeAction()
    {
        parent::initializeAction();

        $this->resultSet = $this->widgetConfiguration['resultSet'];
        $this->groupItem = $this->widgetConfiguration['groupItem'];
        $this->configuration['itemsPerPage'] = $this->getItemsPerPage();

        $this->numberOfPages = (int)ceil($this->groupItem->getAllResultCount() / $this->configuration['itemsPerPage']);
    }

    /**
     * Determines the number of results per page. When nothing is configured 10 will be returned.
     *
     * @return int
     */
    protected function getItemsPerPage()
    {
        $perPage = (int)$this->groupItem->getGroup()->getResultsPerPage();
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
        $groupName = $this->groupItem->getGroup()->getGroupName();
        $groupItemValue = $this->groupItem->getGroupValue();
        $this->currentPage = $this->resultSet->getUsedSearchRequest()->getGroupItemPage($groupName, $groupItemValue);
        if ($this->currentPage < 1) {
            $this->currentPage = 1;
        }
        $this->view->assign('contentArguments', [$this->widgetConfiguration['as'] => $this->groupItem->getSearchResults(), 'pagination' => $this->buildPagination()]);
        $this->view->assign('configuration', $this->configuration);
        $this->view->assign('resultSet', $this->resultSet);
        $this->view->assign('groupItem', $this->groupItem);

        if (!empty($this->templatePath)) {
            $this->view->setTemplatePathAndFilename($this->templatePath);
        }
    }
}
