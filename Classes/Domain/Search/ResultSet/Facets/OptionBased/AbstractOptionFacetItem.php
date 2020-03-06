<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased;

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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetItem;

/**
 * Base class for all facet items that are represented as option
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class AbstractOptionFacetItem extends AbstractFacetItem
{
    /**
     * @var string
     */
    protected $value = '';

    /**
     * @param AbstractFacet $facet
     * @param string $label
     * @param string $value
     * @param int $documentCount
     * @param bool $selected
     * @param array $metrics
     */
    public function __construct(AbstractFacet $facet, $label = '', $value = '', $documentCount = 0, $selected = false, $metrics = [])
    {
        $this->value = $value;
        parent::__construct($facet, $label, $documentCount, $selected, $metrics);
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getUriValue()
    {
        return $this->getValue();
    }

    /**
     * @return string
     */
    public function getCollectionKey()
    {
        return $this->getValue();
    }
}
