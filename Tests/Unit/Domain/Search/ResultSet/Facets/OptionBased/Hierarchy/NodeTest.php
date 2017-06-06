<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy;

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

use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\HierarchyFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\Node;

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
        $this->assertFalse($node->getHasParentNode(), 'Node with unassigned parent node should not indicate that a parent node was assigned');

        $facetMock = $this->getDumbMock(HierarchyFacet::class);
        $parentNode = new Node($facetMock);
        $node = new Node($facetMock, $parentNode);
        $this->assertTrue($node->getHasParentNode(), 'Node with assigned parent node should indicate that');
        $this->assertSame($parentNode, $node->getParentNode(), 'Node did not return assigend parent node');
    }

    /**
     * @test
     */
    public function canGetHasChildNodeSelectedReturnFalseWhenNoChildNodeWasAssigned()
    {
        $facetMock = $this->getDumbMock(HierarchyFacet::class);
        $node = new Node($facetMock);

        $this->assertFalse($node->getHasChildNodeSelected(), 'Node without childnodes should not indicate that it as a selected child node');
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

        $this->assertFalse($node->getHasChildNodeSelected(), 'Node with only unselected childnodes should not indicate that it has a selected child node');
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

        $this->assertTrue($node->getHasChildNodeSelected(), 'Node with selected child node should indicate that it has a selected child node');
    }
}
