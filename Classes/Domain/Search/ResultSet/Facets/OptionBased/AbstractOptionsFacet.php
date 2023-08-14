<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetItemCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;

/**
 * Class AbstractOptionsFacet
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class AbstractOptionsFacet extends AbstractFacet
{
    public function __construct(
        SearchResultSet $resultSet,
        string $name,
        string $field,
        string $label = '',
        array $facetConfiguration = [],
        protected OptionCollection $options = new OptionCollection(),
    ) {
        parent::__construct($resultSet, $name, $field, $label, $facetConfiguration);
    }

    public function getOptions(): OptionCollection
    {
        return $this->options;
    }

    public function setOptions(OptionCollection $options): void
    {
        $this->options = $options;
    }

    public function addOption(AbstractOptionFacetItem $option): void
    {
        $this->options->add($option);
    }

    /**
     * The implementation of this method should return a "flatten" collection of all items.
     */
    public function getAllFacetItems(): AbstractFacetItemCollection
    {
        return $this->options;
    }

    /**
     * Get facet partial name used for rendering the facet
     */
    public function getPartialName(): string
    {
        return !empty($this->facetConfiguration['partialName']) ? $this->facetConfiguration['partialName'] : 'Options';
    }
}
