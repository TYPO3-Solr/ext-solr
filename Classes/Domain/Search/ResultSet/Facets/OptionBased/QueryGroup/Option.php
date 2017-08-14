<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup;

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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\AbstractOptionFacetItem;

/**
 * Value object that represent an option of a queryGroup facet.
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class Option extends AbstractOptionFacetItem
{
    /**
     * @param QueryGroupFacet $facet
     * @param string $label
     * @param string $value
     * @param int $documentCount
     * @param bool $selected
     */
    public function __construct(QueryGroupFacet $facet, $label = '', $value = '', $documentCount = 0, $selected = false)
    {
        parent::__construct($facet, $label, $value, $documentCount, $selected);
    }
}
