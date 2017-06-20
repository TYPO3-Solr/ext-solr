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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Class FacetAddOptionViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\ViewHelpers\Link
 */
class SetFacetItemViewHelper extends AbstractValueViewHelper
{

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        /** @var  $facet AbstractFacet */
        $facet = $arguments['facet'];
        $itemValue = self::getValueFromArguments($arguments);
        $previousRequest = $facet->getResultSet()->getUsedSearchRequest();
        $uri = self::getSearchUriBuilder()->getSetFacetValueUri($previousRequest, $facet->getName(), $itemValue);
        return $uri;
    }
}
