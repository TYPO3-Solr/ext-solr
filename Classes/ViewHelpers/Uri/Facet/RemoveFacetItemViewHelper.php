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
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Class RemoveFacetItemViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RemoveFacetItemViewHelper extends AbstractValueViewHelper
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
        /** @var  $resultSet SearchResultSet */
        $name = self::getNameFromArguments($arguments);
        $itemValue = self::getValueFromArguments($arguments);
        $resultSet = self::getResultSetFromArguments($arguments);
        $previousRequest = $resultSet->getUsedSearchRequest();
        $uri = self::getSearchUriBuilder($renderingContext)->getRemoveFacetValueUri($previousRequest, $name, $itemValue);
        return $uri;
    }
}
