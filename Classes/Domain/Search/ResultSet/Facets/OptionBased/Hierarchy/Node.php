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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\AbstractOptionFacetItem;

/**
 * Value object that represent an option of an options-facet.
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class Node extends AbstractOptionFacetItem
{
    /**
     * @var int
     */
    protected int $depth;

    /**
     * @param HierarchyFacet $facet
     * @param Node|null $parentNode
     * @param string $key
     * @param string $label
     * @param string $value
     * @param int $documentCount
     * @param bool $selected
     * @param NodeCollection $childNodes
     */
    public function __construct(
        HierarchyFacet $facet,
        protected ?Node $parentNode = null,
        protected string $key = '',
        $label = '',
        $value = '',
        $documentCount = 0,
        bool $selected = false,
        protected NodeCollection $childNodes = new NodeCollection(),
    ) {
        parent::__construct(
            $facet,
            $label,
            $value,
            $documentCount,
            $selected,
        );
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
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
     * @return Node|null
     */
    public function getParentNode(): ?Node
    {
        return $this->parentNode;
    }

    /**
     * @return bool
     */
    public function getHasParentNode(): bool
    {
        return $this->parentNode !== null;
    }

    /**
     * @return bool
     */
    public function getHasChildNodeSelected(): bool
    {
        /** @var Node $childNode */
        foreach ($this->childNodes as $childNode) {
            if ($childNode->getSelected()) {
                return true;
            }
        }
        return false;
    }
}
