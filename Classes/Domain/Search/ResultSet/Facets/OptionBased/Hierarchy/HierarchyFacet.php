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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetItemCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Value object that represent the options facet.
 */
class HierarchyFacet extends AbstractFacet
{
    public const TYPE_HIERARCHY = 'hierarchy';

    protected static string $type = self::TYPE_HIERARCHY;

    protected array $nodesByKey = [];

    public function __construct(
        SearchResultSet $resultSet,
        string $name,
        string $field,
        string $label = '',
        array $facetConfiguration = [],
        protected NodeCollection $childNodes = new NodeCollection(),
        protected NodeCollection $allNodes = new NodeCollection(),
    ) {
        parent::__construct($resultSet, $name, $field, $label, $facetConfiguration);
    }

    public function addChildNode(Node $node): void
    {
        $this->childNodes->add($node);
    }

    public function getChildNodes(): NodeCollection
    {
        return $this->childNodes;
    }

    /**
     * Creates a new node on the right position with the right parent node.
     */
    public function createNode(
        ?string $parentKey,
        string $key,
        string $label,
        string $value,
        int $count,
        bool $selected,
    ): void {
        /** @var Node|null $parentNode */
        $parentNode = $parentKey !== null ? ($this->nodesByKey[$parentKey] ?? null) : null;
        /** @var Node $node */
        $node = GeneralUtility::makeInstance(
            Node::class,
            $this,
            $parentNode,
            $key,
            $label,
            $value,
            $count,
            $selected,
        );
        $this->nodesByKey[$key] = $node;

        if ($parentNode === null) {
            $this->addChildNode($node);
        } else {
            $parentNode->addChildNode($node);
        }

        $this->allNodes->add($node);
    }

    /**
     * Get facet partial name used for rendering the facet
     */
    public function getPartialName(): string
    {
        return !empty($this->facetConfiguration['partialName']) ? $this->facetConfiguration['partialName'] : 'Hierarchy';
    }

    /**
     * The implementation of this method should return a "flatten" collection of all items.
     */
    public function getAllFacetItems(): AbstractFacetItemCollection
    {
        return $this->allNodes;
    }
}
