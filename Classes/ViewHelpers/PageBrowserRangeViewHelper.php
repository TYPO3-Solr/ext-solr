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

/**
 * Class PageBrowserRangeViewHelper
 */
class PageBrowserRangeViewHelper extends AbstractSolrFrontendViewHelper
{
    /**
     * Initializes the arguments
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('from', 'string', 'from', false, 'from');
        $this->registerArgument('to', 'string', 'to', false, 'to');
        $this->registerArgument('total', 'string', 'total', false, 'total');
    }

    /**
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function render()
    {
        $from = $this->arguments['from'];
        $to = $this->arguments['to'];
        $total = $this->arguments['total'];

        $resultSet = self::getUsedSearchResultSetFromRenderingContext($this->renderingContext);
        $search = $resultSet->getUsedSearch();
        $variableProvider = $this->renderingContext->getVariableProvider();

        $numberOfResultsOnPage = $resultSet->getSearchResults()->getCount();
        $numberOfAllResults = $resultSet->getAllResultCount();

        $resultsFrom = ($search->getResponseBody() ? $search->getResponseBody()->start : 0) + 1;
        $resultsTo = $resultsFrom + $numberOfResultsOnPage - 1;
        $variableProvider->add($from, $resultsFrom);
        $variableProvider->add($to, $resultsTo);
        $variableProvider->add($total, $numberOfAllResults);

        $content = $this->renderChildren();

        $variableProvider->remove($from);
        $variableProvider->remove($to);
        $variableProvider->remove($total);

        return $content;
    }
}
