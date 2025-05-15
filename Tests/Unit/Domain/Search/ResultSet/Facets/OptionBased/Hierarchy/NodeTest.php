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
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

use function array_map;

/**
 * Testcase to test the Node class
 */
class NodeTest extends SetUpUnitTestCase
{
    #[Test]
    public function canGetHasParentNode(): void
    {
        $facetMock = $this->createMock(HierarchyFacet::class);
        $node = new Node($facetMock);
        self::assertFalse($node->getHasParentNode(), 'Node with unassigned parent node should not indicate that a parent node was assigned');

        $facetMock = $this->createMock(HierarchyFacet::class);
        $parentNode = new Node($facetMock);
        $node = new Node($facetMock, $parentNode);
        self::assertTrue($node->getHasParentNode(), 'Node with assigned parent node should indicate that');
        self::assertSame($parentNode, $node->getParentNode(), 'Node did not return assigend parent node');
    }

    #[Test]
    #[DataProvider('provideGetHasChildNodeSelectedDataSet')]
    public function canHandleGetHasChildNodeSelected(
        bool $expectedResult,
        array $childNodes,
    ): void {
        $node = $this->convertDataToNode(['children' => $childNodes]);

        self::assertSame($expectedResult, $node->getHasChildNodeSelected());
    }

    private function convertDataToNode(array $data): Node
    {
        $node = new Node(
            $this->createMock(HierarchyFacet::class),
            null,
            '',
            '',
            '',
            0,
            $data['selected'] ?? false,
        );

        foreach (array_map([$this, 'convertDataToNode'], $data['children'] ?? []) as $childNode) {
            $node->addChildNode($childNode);
        }

        return $node;
    }

    public static function provideGetHasChildNodeSelectedDataSet(): iterable
    {
        yield 'No child nodes' => [false, []];
        yield 'One direct child node: selected' => [true, [['selected' => true]]];
        yield 'One direct child node: not selected' => [false, [['selected' => false]]];
        yield 'Child node 1 level down: selected' => [true, [['selected' => false, 'children' => [['selected' => true]]]]];
        yield 'Child node 2 levels down: selected' => [true, [['selected' => false, 'children' => [['selected' => false, 'children' => [['selected' => true]]]]]]];
    }
}
