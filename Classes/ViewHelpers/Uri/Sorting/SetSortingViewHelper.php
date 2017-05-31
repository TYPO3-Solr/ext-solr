<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Sorting;

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
use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\AbstractUriViewHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3\CMS\Fluid\Core\ViewHelper\Facets\CompilableInterface;

/**
 * Class SetSortingViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\ViewHelpers\Link
 */
class SetSortingViewHelper extends AbstractUriViewHelper implements CompilableInterface
{

    /**
     * @param string $sortingName
     * @param string $sortingDirection
     * @return string
     */
    public function render($sortingName, $sortingDirection)
    {
        return self::renderStatic(
            ['sortingName' => $sortingName, 'sortingDirection' => $sortingDirection],
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
        $sortingName = $arguments['sortingName'];
        $sortingDirection = $arguments['sortingDirection'];
        $previousRequest = $renderingContext->getControllerContext()->getSearchResultSet()->getUsedSearchRequest();

        $uri = self::getSearchUriBuilder()->getSetSortingUri($previousRequest, $sortingName, $sortingDirection);
        return $uri;
    }
}
