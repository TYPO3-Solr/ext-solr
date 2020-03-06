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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;


/**
 * Class ResultPaginateController
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ResultPaginateController extends AbstractPaginateWidgetController
{

    /**
     * @var SearchResultSet
     */
    protected $resultSet;

    /**
     * @return void
     */
    public function initializeAction()
    {
        parent::initializeAction();

        $this->resultSet = $this->widgetConfiguration['resultSet'];
        $this->configuration['itemsPerPage'] = $this->getItemsPerPage();

        $this->numberOfPages = (int)ceil($this->resultSet->getAllResultCount() / $this->configuration['itemsPerPage']);
    }

    /**
     * Determines the number of results per page. When nothing is configured 10 will be returned.
     *
     * @return int
     */
    protected function getItemsPerPage()
    {
        $perPage = (int)$this->resultSet->getUsedSearch()->getQuery()->getRows();
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
        $this->currentPage = $this->resultSet->getUsedPage();
        if ($this->currentPage < 1) {
            $this->currentPage = 1;
        }
        $this->view->assign('contentArguments', [$this->widgetConfiguration['as'] => $this->resultSet->getSearchResults(), 'pagination' => $this->buildPagination()]);
        $this->view->assign('configuration', $this->configuration);
        $this->view->assign('resultSet', $this->resultSet);
        if (!empty($this->templatePath)) {
            $this->view->setTemplatePathAndFilename($this->templatePath);
        }
    }
}
