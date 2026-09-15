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
 */
class Node extends AbstractOptionFacetItem
{
    protected int $depth = 0;

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

        if ($parentNode) {
            $this->depth = $parentNode->getDepth() + 1;
        }
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function addChildNode(Node $node): void
    {
        $this->childNodes->add($node);
    }

    public function getChildNodes(): NodeCollection
    {
        return $this->childNodes;
    }

    public function getParentNode(): ?Node
    {
        return $this->parentNode;
    }

    public function getHasParentNode(): bool
    {
        return $this->parentNode !== null;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getHasChildNodeSelected(): bool
    {
        /** @var Node $childNode */
        foreach ($this->childNodes as $childNode) {
            if ($childNode->getSelected() || $childNode->getHasChildNodeSelected()) {
                return true;
            }
        }
        return false;
    }
}
