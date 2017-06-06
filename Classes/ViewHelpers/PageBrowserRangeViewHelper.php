<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers;

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
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3\CMS\Fluid\Core\ViewHelper\Facets\CompilableInterface;

/**
 * Class PageBrowserRangeViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de> *
 * @package ApacheSolrForTypo3\Solr\ViewHelpers
 */
class PageBrowserRangeViewHelper extends AbstractViewHelper implements CompilableInterface
{

    /**
     * @param string $from variable name for from value
     * @param string $to variable name for to value
     * @param string $total variable name for total value
     * @return string
     * @throws \TYPO3\CMS\Fluid\Core\ViewHelper\Exception\InvalidVariableException
     */
    public function render($from = 'from', $to = 'to', $total = 'total')
    {
        return self::renderStatic(
            [
                'from' => $from,
                'to' => $to,
                'total' => $total
            ],
            $this->buildRenderChildrenClosure(),
            $this->renderingContext
        );
    }

    /**
     * @param array $arguments
     * @param callable $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $from = $arguments['from'];
        $to = $arguments['to'];
        $total = $arguments['total'];

        $search = $renderingContext->getControllerContext()->getSearchResultSet()->getUsedSearch();
        $variableProvider = $renderingContext->getVariableProvider();

        $resultsFrom = $search->getResponseBody()->start + 1;
        $resultsTo = $resultsFrom + count($search->getResultDocumentsRaw()) - 1;
        $variableProvider->add($from, $resultsFrom);
        $variableProvider->add($to, $resultsTo);
        $variableProvider->add($total, $search->getNumberOfResults());

        $content = $renderChildrenClosure();

        $variableProvider->remove($from);
        $variableProvider->remove($to);
        $variableProvider->remove($total);

        return $content;
    }
}
