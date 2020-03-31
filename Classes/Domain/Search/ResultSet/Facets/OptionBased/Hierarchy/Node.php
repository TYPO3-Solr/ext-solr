<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy;

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
 * Value object that represent an option of a options facet.
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class Node extends AbstractOptionFacetItem
{

    /**
     * @var NodeCollection
     */
    protected $childNodes;

    /**
     * @var Node
     */
    protected $parentNode;

    /**
     * @var integer
     */
    protected $depth;

    /**
     * @var string
     */
    protected $key;

    /**
     * @param HierarchyFacet $facet
     * @param Node $parentNode
     * @param string $key
     * @param string $label
     * @param string $value
     * @param int $documentCount
     * @param bool $selected
     */
    public function __construct(HierarchyFacet $facet, $parentNode = null, $key = '', $label = '', $value = '', $documentCount = 0, $selected = false)
    {
        parent::__construct($facet, $label, $value, $documentCount, $selected);
        $this->value = $value;
        $this->childNodes = new NodeCollection();
        $this->parentNode = $parentNode;
        $this->key = $key;
    }

    /**
     * @return string
     */
    public function getKey()
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
    public function getChildNodes()
    {
        return $this->childNodes;
    }

    /**
     * @return Node|null
     */
    public function getParentNode()
    {
        return $this->parentNode;
    }

    /**
     * @return bool
     */
    public function getHasParentNode()
    {
        return $this->parentNode !== null;
    }

    /**
     * @return bool
     */
    public function getHasChildNodeSelected()
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
