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
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\AbstractUriViewHelper;

/**
 * Class AbstractValueViewHelper
 */
abstract class AbstractValueViewHelper extends AbstractUriViewHelper
{
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
     * Extracts and Returns value from given arguments.
     */
    protected static function getValueFromArguments(array $arguments = []): string
    {
        if (isset($arguments['facetItem'])) {
            /** @var AbstractFacetItem $facetItem */
            $facetItem = $arguments['facetItem'];
            $facetValue = $facetItem->getUriValue();
        } elseif (isset($arguments['facetItemValue'])) {
            $facetValue = $arguments['facetItemValue'];
        } else {
            throw new InvalidArgumentException(
                'No facetItem was passed, please pass either facetItem or facetItemValue',
                9637361100,
            );
        }

        return $facetValue;
    }

    /**
     * Extracts and returns name from arguments.
     */
    protected static function getNameFromArguments(array $arguments = []): string
    {
        if (isset($arguments['facet'])) {
            /** @var AbstractFacet $facet */
            $facet = $arguments['facet'];
            $facetName = $facet->getName();
        } elseif (isset($arguments['facetName'])) {
            $facetName = $arguments['facetName'];
        } else {
            throw new InvalidArgumentException(
                'No facet was passed, please pass either facet or facetName',
                1680615971,
            );
        }

        return $facetName;
    }

    /**
     * Extracts and returns result-set from arguments.
     */
    protected static function getResultSetFromArguments(array $arguments = []): SearchResultSet
    {
        if (isset($arguments['facet'])) {
            /** @var AbstractFacet $facet */
            $facet = $arguments['facet'];
            $resultSet = $facet->getResultSet();
        } elseif (isset($arguments['facetName'])) {
            $resultSet = $arguments['resultSet'];
        } else {
            throw new InvalidArgumentException(
                'No facet was passed, please pass either facet or resultSet',
                6037735115,
            );
        }

        return $resultSet;
    }
}
