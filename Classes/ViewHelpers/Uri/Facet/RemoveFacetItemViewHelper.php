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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetItem;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\AbstractUriViewHelper;

class RemoveFacetItemViewHelper extends AbstractUriViewHelper
{
    use ValueViewHelperArgumentTrait;

    /**
     * @inheritdoc
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument('facet', AbstractFacet::class, 'The facet');
        $this->registerArgument('facetName', 'string', 'The facet name');
        $this->registerArgument('facetItem', AbstractFacetItem::class, 'The facet item');
        $this->registerArgument('facetItemValue', 'string', 'The facet item');
        $this->registerArgument('resultSet', SearchResultSet::class, 'The result set');
    }

    /**
     * Renders URI for removing the facet item.
     */
    public function render(): string
    {
        $name = $this->getNameFromArguments($this->arguments);
        $itemValue = $this->getValueFromArguments($this->arguments);
        $resultSet = $this->getResultSetFromArguments($this->arguments);
        $previousRequest = $resultSet->getUsedSearchRequest();

        return $this->getSearchUriBuilder($this->renderingContext)->getRemoveFacetValueUri(
            $previousRequest,
            $name,
            $itemValue,
        );
    }
}
