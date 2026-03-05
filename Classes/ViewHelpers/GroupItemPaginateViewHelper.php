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

namespace ApacheSolrForTypo3\Solr\ViewHelpers;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Pagination\ResultsPagination;
use ApacheSolrForTypo3\Solr\Pagination\ResultsPaginator;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;

final class GroupItemPaginateViewHelper extends AbstractSolrViewHelper
{
    private const LAYOUT_ROOT_PATH = 'EXT:solr/Resources/Private/Layouts/ViewHelpers/';

    private const PARTIAL_ROOT_PATH = 'EXT:solr/Resources/Private/Partials/ViewHelpers/';

    private const TEMPLATE_ROOT_PATH = 'EXT:solr/Resources/Private/Templates/ViewHelpers/';

    protected $escapeChildren = false;
    protected $escapeOutput = false;

    public function __construct(
        private readonly ViewFactoryInterface $viewFactory,
    ) {}

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('resultSet', SearchResultSet::class, 'resultSet', true);
        $this->registerArgument('groupItem', GroupItem::class, 'groupItem', true);
        $this->registerArgument('as', 'string', 'as', false, 'documents');
        $this->registerArgument('configuration', 'array', 'configuration', false, ['insertAbove' => true, 'insertBelow' => true, 'maximumNumberOfLinks' => 10]);
    }

    public function render(): string
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
                'groupItem' => $this->arguments['groupItem'],
            ],
        );

        $paginationRendered = $paginationView->render('GroupItemPaginate/Index');

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

    private function getTemplateObject(): ViewInterface
    {
        $configuration = $this->getSearchResultSet()->getUsedSearchRequest()->getContextTypoScriptConfiguration();
        $viewConfiguration = $configuration->getValueByPath('plugin.tx_solr.view.');

        $layoutRootPaths = [];
        $layoutRootPaths[] = GeneralUtility::getFileAbsFileName(self::LAYOUT_ROOT_PATH);
        if (isset($viewConfiguration['layoutRootPaths.'])) {
            foreach ($viewConfiguration['layoutRootPaths.'] as $layoutRootPath) {
                $layoutRootPaths[] = GeneralUtility::getFileAbsFileName(rtrim($layoutRootPath, '/') . '/ViewHelpers/');
            }
        }
        $partialRootPaths = [];
        $partialRootPaths[] = GeneralUtility::getFileAbsFileName(self::PARTIAL_ROOT_PATH);
        if (isset($viewConfiguration['partialRootPaths.'])) {
            foreach ($viewConfiguration['partialRootPaths.'] as $partialRootPath) {
                $partialRootPaths[] = GeneralUtility::getFileAbsFileName(rtrim($partialRootPath, '/') . '/ViewHelpers/');
            }
        }
        $templateRootPaths = [];
        $templateRootPaths[] = GeneralUtility::getFileAbsFileName(self::TEMPLATE_ROOT_PATH);
        if (isset($viewConfiguration['templateRootPaths.'])) {
            foreach ($viewConfiguration['templateRootPaths.'] as $templateRootPath) {
                $templateRootPaths[] = GeneralUtility::getFileAbsFileName(rtrim($templateRootPath, '/') . '/ViewHelpers/');
            }
        }

        $request = $this->renderingContext->getAttribute(ServerRequestInterface::class);

        return $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: $templateRootPaths,
            partialRootPaths: $partialRootPaths,
            layoutRootPaths: $layoutRootPaths,
            request: $request,
            format: 'html',
        ));
    }

    /**
     * Determines the number of results per page. When nothing is configured, 10 will be returned.
     */
    private function getItemsPerPage(): int
    {
        $perPage = (int)$this->arguments['groupItem']->getGroup()->getResultsPerPage();
        return $perPage > 0 ? $perPage : 10;
    }

    private function getSearchResultSet(): SearchResultSet
    {
        return $this->arguments['resultSet'];
    }
}
