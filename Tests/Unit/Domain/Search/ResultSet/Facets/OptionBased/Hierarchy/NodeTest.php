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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\HierarchyFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\Node;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase to test the Node class
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class NodeTest extends UnitTest
{

    /**
     * @test
     */
    public function canGetHasParentNode()
    {
        $facetMock = $this->getDumbMock(HierarchyFacet::class);
        $node = new Node($facetMock);
        self::assertFalse($node->getHasParentNode(), 'Node with unassigned parent node should not indicate that a parent node was assigned');

        $facetMock = $this->getDumbMock(HierarchyFacet::class);
        $parentNode = new Node($facetMock);
        $node = new Node($facetMock, $parentNode);
        self::assertTrue($node->getHasParentNode(), 'Node with assigned parent node should indicate that');
        self::assertSame($parentNode, $node->getParentNode(), 'Node did not return assigend parent node');
    }

    /**
     * @test
     */
    public function canGetHasChildNodeSelectedReturnFalseWhenNoChildNodeWasAssigned()
    {
        $facetMock = $this->getDumbMock(HierarchyFacet::class);
        $node = new Node($facetMock);

        self::assertFalse($node->getHasChildNodeSelected(), 'Node without childnodes should not indicate that it as a selected child node');
    }

    /**
     * @test
     */
    public function canGetHasChildNodeSelectedReturnFalseWhenNoSelectedChildNodeWasAssigned()
    {
        $facetMock = $this->getDumbMock(HierarchyFacet::class);
        $node = new Node($facetMock);

        $childNode = new Node($facetMock, $node);
        $node->addChildNode($childNode);

        self::assertFalse($node->getHasChildNodeSelected(), 'Node with only unselected childnodes should not indicate that it has a selected child node');
    }

    /**
     * @test
     */
    public function canGetHasChildNodeSelectedReturnTrueWhenSelectedChildNodeWasAssigned()
    {
        $facetMock = $this->getDumbMock(HierarchyFacet::class);
        $node = new Node($facetMock);

        $selectedChildNode = new Node($facetMock, $node, '', '', '', 0, true);
        $node->addChildNode($selectedChildNode);

        self::assertTrue($node->getHasChildNodeSelected(), 'Node with selected child node should indicate that it has a selected child node');
    }
}
