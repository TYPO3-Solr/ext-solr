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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\Node;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\HierarchyFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\HierarchyFacetParser;
use ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets\AbstractFacetParserTest;

/**
 * Class HierarchyFacetParserTest
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Frans Saris <frans@beech.it>
 */
class HierarchyFacetParserTest extends AbstractFacetParserTest
{

    /**
     * @test
     */
    public function facetIsCreated()
    {
        $facetConfiguration = [
            'pageHierarchy.' => [
                'type' => 'hierarchy',
                'label' => 'Rootline',
                'field' => 'rootline',
            ]
        ];

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_hierarchy_facet.json',
            $facetConfiguration
        );

        /** @var $parser HierarchyFacetParser */
        $parser = $this->getInitializedParser(HierarchyFacetParser::class);
        $facet = $parser->parse($searchResultSet, 'pageHierarchy', $facetConfiguration['pageHierarchy.']);
        $this->assertInstanceOf(HierarchyFacet::class, $facet);

        // on the rootlevel there should only be one childNode
        $this->assertSame(1, $facet->getChildNodes()->getCount());
        $this->assertSame(8, $facet->getChildNodes()->getByPosition(0)->getChildNodes()->getCount());

        $firstNode = $facet->getChildNodes()->getByPosition(0)->getChildNodes()->getByPosition(0);
        $this->assertSame('/1/14/', $firstNode->getValue());
        $this->assertSame('14', $firstNode->getKey());
    }

    /**
     * @return array
     */
    public function dataProviderForDeepMoreThen10DoesNotBreakHierarchyFacet()
    {
        return [
            'sortByCount' => [
                [
                    'pageHierarchy.' => [
                        'type' => 'hierarchy',
                        'label' => 'Rootline',
                        'field' => 'rootline',
                        'sortBy' => 'count'
                    ]
                ],
                'fake_solr_response_with_deep_more_then_10_hierarchy_facet_sorted_by_count.json'
            ],
            'sortByIndex' => [
                [
                    'pageHierarchy.' => [
                        'type' => 'hierarchy',
                        'label' => 'Rootline',
                        'field' => 'rootline',
                        'sortBy' => 'index'
                    ]
                ],
                'fake_solr_response_with_deep_more_then_10_hierarchy_facet_sorted_by_index.json'
            ]
        ];
    }

    /**
     * @test
     * @dataProvider dataProviderForDeepMoreThen10DoesNotBreakHierarchyFacet
     */
    public function deepMoreThen10DoesNotBreakHierarchyFacet(array $facetConfiguration, string $fixtureFile)
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            $fixtureFile,
            $facetConfiguration
        );

        /** @var $parser HierarchyFacetParser */
        $parser = $this->getInitializedParser(HierarchyFacetParser::class);
        $facet = $parser->parse($searchResultSet, 'pageHierarchy', $facetConfiguration['pageHierarchy.']);

        $this->assertInstanceOf(HierarchyFacet::class, $facet);

        $this->assertSame(1, $facet->getChildNodes()->getCount(), 'The hierarchy facet is broken. Expected that no node has more than one child node.');
        $this->assertNoNodeHasMoreThanOneChildInTheHierarchy($facet->getChildNodes()->getByPosition(0));
    }

    /**
     * Traverses the hierarchy facet and checks if some has more than one child.
     *
     * @param Node $node
     */
    protected function assertNoNodeHasMoreThanOneChildInTheHierarchy(Node $node)
    {
        $this->assertLessThanOrEqual(1, $node->getChildNodes()->getCount(), 'The hierarchy facet is broken. Expected that no node has more than one child node.');
        if ($node->getChildNodes()->getCount() === 0) {
            return;
        }
        $this->assertNoNodeHasMoreThanOneChildInTheHierarchy($node->getChildNodes()->getByPosition(0));
    }

    /**
     * @test
     */
    public function facetIsNotActive()
    {
        $facetConfiguration = [
            'pageHierarchy.' => [
                'type' => 'hierarchy',
                'label' => 'Rootline',
                'field' => 'rootline',
            ]
        ];

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_hierarchy_facet.json',
            $facetConfiguration
        );

            /** @var $parser HierarchyFacetParser */
        $parser = $this->getInitializedParser(HierarchyFacetParser::class);
        $facet = $parser->parse($searchResultSet, 'pageHierarchy', $facetConfiguration['pageHierarchy.']);
        $this->assertFalse($facet->getIsUsed());
    }

    /**
     * @test
     */
    public function facetIsActive()
    {
        $facetConfiguration = [
            'pageHierarchy.' => [
                'type' => 'hierarchy',
                'label' => 'Rootline',
                'field' => 'rootline',
            ]
        ];

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_hierarchy_facet.json',
            $facetConfiguration,
            ['pageHierarchy:/1/14/']
        );

        /** @var $parser HierarchyFacetParser */
        $parser = $this->getInitializedParser(HierarchyFacetParser::class);
        $facet = $parser->parse($searchResultSet, 'pageHierarchy', $facetConfiguration['pageHierarchy.']);

        $selectedFacetByUrl = $facet->getChildNodes()->getByPosition(0)->getChildNodes()->getByPosition(0);
        $this->assertTrue($facet->getIsUsed());

        $valueSelectedItem = $selectedFacetByUrl->getLabel();
        $this->assertSame('14', $valueSelectedItem, 'Unpexcted value for selected item');

        $subItemCountOfSelectedFacet = $selectedFacetByUrl->getChildNodes()->count();
        $this->assertSame(15, $subItemCountOfSelectedFacet, 'Expected to have 15 sub items below path /1/14/');
    }
}
