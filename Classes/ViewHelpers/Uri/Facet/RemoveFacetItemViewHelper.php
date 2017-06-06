<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Facet;

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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionsFacet\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionsFacet\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\Uri\SearchUriBuilder;
use ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractTagBasedViewHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3\CMS\Fluid\Core\ViewHelper\Facets\CompilableInterface;

/**
 * Class FacetAddOptionViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\ViewHelpers\Link
 */
class RemoveFacetItemViewHelper extends AbstractValueViewHelper implements CompilableInterface
{

    /**
     * @param array $arguments
     * @param callable $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        /** @var  $facet OptionsFacet */
        $facet = $arguments['facet'];
        $itemValue = self::getValueFromArguments($arguments);
        $previousRequest = $facet->getResultSet()->getUsedSearchRequest();
        $uri = self::getSearchUriBuilder()->getRemoveFacetValueUri($previousRequest, $facet->getName(), $itemValue);
        return $uri;
    }
}
