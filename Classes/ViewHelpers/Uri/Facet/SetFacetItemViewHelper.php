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

/**
 * Class SetFacetItemViewHelper
 */
class SetFacetItemViewHelper extends AbstractValueViewHelper
{
    /**
     * Renders URI for setting the facet item.
     */
    public function render()
    {
        $name = self::getNameFromArguments($this->arguments);
        $itemValue = self::getValueFromArguments($this->arguments);
        $resultSet = self::getResultSetFromArguments($this->arguments);
        $previousRequest = $resultSet->getUsedSearchRequest();

        return self::getSearchUriBuilder($this->renderingContext)->getSetFacetValueUri($previousRequest, $name, $itemValue);
    }
}
