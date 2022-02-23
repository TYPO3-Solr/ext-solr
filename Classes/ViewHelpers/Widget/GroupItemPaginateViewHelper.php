<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Widget;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Frans Saris <frans@beech.it> & Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Pagination\ResultsPagination;
use ApacheSolrForTypo3\Solr\Pagination\ResultsPaginator;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrViewHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Class GroupItemPaginateViewHelper
 */
class GroupItemPaginateViewHelper extends AbstractSolrViewHelper
{

    protected $escapeChildren = false;
    protected $escapeOutput = false;

    /**
     * Initializes the arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('resultSet', SearchResultSet::class, 'resultSet', true);
        $this->registerArgument('groupItem', GroupItem::class, 'groupItem', true);
        $this->registerArgument('as', 'string', 'as', false, 'documents');
        $this->registerArgument('configuration', 'array', 'configuration', false, ['insertAbove' => true, 'insertBelow' => true, 'maximumNumberOfLinks' => 10]);
    }

    public function render()
    {
        $itemsPerPage = $this->getItemsPerPage();
        $configuration = $this->arguments['configuration'];
        $groupName = $this->arguments['groupItem']->getGroup()->getGroupName();
        $groupItemValue = $this->arguments['groupItem']->getGroupValue();
        $currentPage = $this->arguments['resultSet']->getUsedSearchRequest()->getGroupItemPage($groupName, $groupItemValue);
        if ($currentPage < 1) {
            $currentPage = 1;
        }

        $paginator = new ResultsPaginator($this->arguments['groupItem'], $currentPage, $itemsPerPage);
        $pagination = new ResultsPagination($paginator);
        $pagination->setMaxPageNumbers((int)$configuration['maximumNumberOfLinks']);

        $paginationView = $this->getTemplateObject();
        $paginationView->assignMultiple(
            [
                'paginator' => $paginator,
                'pagination' => $pagination,
                'configuration' => $configuration,
                'resultSet' => $this->arguments['resultSet'],
                'groupItem' => $this->arguments['groupItem']
            ]
        );

        $paginationRendered = $paginationView->render();

        $variableProvider = $this->renderingContext->getVariableProvider();
        $variableProvider->add('paginator', $paginator);
        $variableProvider->add($this->arguments['as'], $paginator->getPaginatedItems());

        $contents = [];
        $contents[] = $configuration['insertAbove'] ? $paginationRendered : '';
        $contents[] = $this->renderChildren();
        $contents[] = $configuration['insertBelow'] ? $paginationRendered : '';

        $variableProvider->remove($this->arguments['as']);
        $variableProvider->remove('paginator');

        return implode('', $contents);
    }

    protected function getTemplateObject(): StandaloneView
    {
        $setup = $this->getConfigurationManager()->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);

        $layoutRootPaths = [];
        $layoutRootPaths[] = GeneralUtility::getFileAbsFileName('EXT:solr/Resources/Private/Layouts/ViewHelpers/');
        if (isset($setup['plugin.']['tx_solr.']['view.']['layoutRootPaths.'])) {
            foreach ($setup['plugin.']['tx_solr.']['view.']['layoutRootPaths.'] as $layoutRootPath) {
                $layoutRootPaths[] = GeneralUtility::getFileAbsFileName(rtrim($layoutRootPath, '/') . '/ViewHelpers/Widget/');
            }
        }
        $partialRootPaths = [];
        $partialRootPaths[] = GeneralUtility::getFileAbsFileName('EXT:solr/Resources/Private/Partials/ViewHelpers/');
        if (isset($setup['plugin.']['tx_solr.']['view.']['partialRootPaths.'])) {
            foreach ($setup['plugin.']['tx_solr.']['view.']['partialRootPaths.'] as $partialRootPath) {
                $partialRootPaths[] = GeneralUtility::getFileAbsFileName(rtrim($partialRootPath, '/') . '/ViewHelpers/Widget/');
            }
        }
        $templateRootPaths = [];
        $templateRootPaths[] = GeneralUtility::getFileAbsFileName('EXT:solr/Resources/Private/Templates/ViewHelpers/');
        if (isset($setup['plugin.']['tx_solr.']['view.']['templateRootPaths.'])) {
            foreach ($setup['plugin.']['tx_solr.']['view.']['templateRootPaths.'] as $templateRootPath) {
                $templateRootPaths[] = GeneralUtility::getFileAbsFileName(rtrim($templateRootPath, '/') . '/ViewHelpers/Widget/');
            }
        }

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setLayoutRootPaths($layoutRootPaths);
        $view->setPartialRootPaths($partialRootPaths);
        $view->setTemplateRootPaths($templateRootPaths);
        $view->setTemplate('GroupItemPaginate/Index');

        return $view;
    }
    /**
     * Determines the number of results per page. When nothing is configured 10 will be returned.
     */
    protected function getItemsPerPage(): int
    {
        $perPage = (int)$this->arguments['groupItem']->getGroup()->getResultsPerPage();
        return $perPage > 0 ? $perPage : 10;
    }

    protected function getConfigurationManager(): ConfigurationManagerInterface
    {
        return GeneralUtility::getContainer()->get(ConfigurationManager::class);
    }
}
