<?php

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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Facet;

use Closure;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Class SetFacetItemViewHelper
 */
class SetFacetItemViewHelper extends AbstractValueViewHelper
{
    /**
     * Renders URI for setting the facet item.
     */
    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext,
    ) {
        $name = self::getNameFromArguments($arguments);
        $itemValue = self::getValueFromArguments($arguments);
        $resultSet = self::getResultSetFromArguments($arguments);
        $previousRequest = $resultSet->getUsedSearchRequest();

        return self::getSearchUriBuilder($renderingContext)->getSetFacetValueUri($previousRequest, $name, $itemValue);
    }
}
