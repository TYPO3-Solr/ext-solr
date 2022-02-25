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
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class HierarchyFacet extends AbstractFacet
{
    const TYPE_HIERARCHY = 'hierarchy';

    /**
     * String
     * @var string
     */
    protected static string $type = self::TYPE_HIERARCHY;

    /**
     * @var NodeCollection
     */
    protected NodeCollection $childNodes;

    /**
     * @var NodeCollection
     */
    protected NodeCollection $allNodes;

    /**
     * @var array
     */
    protected array $nodesByKey = [];

    /**
     * OptionsFacet constructor
     *
     * @param SearchResultSet $resultSet
     * @param string $name
     * @param string $field
     * @param string $label
     * @param array $configuration Facet configuration passed from typoscript
     */
    public function __construct(
        SearchResultSet $resultSet,
        string $name,
        string $field,
        string $label = '',
        array $configuration = []
    ) {
        parent::__construct($resultSet, $name, $field, $label, $configuration);
        $this->childNodes = new NodeCollection();
        $this->allNodes = new NodeCollection();
    }

    /**
     * @param Node $node
     */
    public function addChildNode(Node $node)
    {
        $this->childNodes->add($node);
    }

    /**
     * @return NodeCollection
     */
    public function getChildNodes(): NodeCollection
    {
        return $this->childNodes;
    }

    /**
     * Creates a new node on the right position with the right parent node.
     *
     * @param string|null $parentKey
     * @param string $key
     * @param string $label
     * @param string $value
     * @param int $count
     * @param bool $selected
     */
    public function createNode(
        ?string $parentKey,
        string $key,
        string $label,
        string $value,
        int $count,
        bool $selected
    ) {
        /* @var $parentNode Node|null */
        $parentNode = $this->nodesByKey[$parentKey] ?? null;
        /* @var Node $node */
        $node = GeneralUtility::makeInstance(
            Node::class,
            $this,
            $parentNode,
            $key,
            $label,
            $value,
            $count,
            $selected
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
     *
     * @return string
     */
    public function getPartialName(): string
    {
        return !empty($this->configuration['partialName']) ? $this->configuration['partialName'] : 'Hierarchy';
    }

    /**
     * The implementation of this method should return a "flatten" collection of all items.
     *
     * @return AbstractFacetItemCollection
     */
    public function getAllFacetItems(): AbstractFacetItemCollection
    {
        return $this->allNodes;
    }
}
