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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\AbstractOptionFacetItem;

/**
 * Value object that represent an option of a queryGroup facet.
 */
class Option extends AbstractOptionFacetItem
{
    public function __construct(
        QueryGroupFacet $facet,
        string $label = '',
        string $value = '',
        int $documentCount = 0,
        bool $selected = false,
        array $metrics = [],
    ) {
        parent::__construct(
            $facet,
            $label,
            $value,
            $documentCount,
            $selected,
            $metrics,
        );
    }
}
