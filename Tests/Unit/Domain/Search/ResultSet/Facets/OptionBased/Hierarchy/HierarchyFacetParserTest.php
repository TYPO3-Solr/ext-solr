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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\HierarchyFacetParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\Node;
use ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\SetUpFacetParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;

/**
 * Class HierarchyFacetParserTest
 */
class HierarchyFacetParserTest extends SetUpFacetParser
{
    #[Test]
    public function facetIsCreated(): void
    {
        $facetConfiguration = [
            'pageHierarchy.' => [
                'type' => 'hierarchy',
                'label' => 'Rootline',
                'field' => 'rootline',
            ],
        ];

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_hierarchy_facet.json',
            $facetConfiguration,
        );

        /** @var HierarchyFacetParser $parser */
        $parser = $this->getInitializedParser(HierarchyFacetParser::class);
        $facet = $parser->parse($searchResultSet, 'pageHierarchy', $facetConfiguration['pageHierarchy.']);
        self::assertInstanceOf(HierarchyFacet::class, $facet);

        // on the rootlevel there should only be one childNode
        self::assertSame(1, $facet->getChildNodes()->getCount());
        self::assertSame(8, $facet->getChildNodes()->getByPosition(0)->getChildNodes()->getCount());

        $firstNode = $facet->getChildNodes()->getByPosition(0)->getChildNodes()->getByPosition(0);
        self::assertSame('/1/14/', $firstNode->getValue());
        self::assertSame('14', $firstNode->getKey());
    }

    public static function dataProviderForDeepMoreThen10DoesNotBreakHierarchyFacet(): Traversable
    {
        yield 'sortByCount' => [
            [
                'pageHierarchy.' => [
                    'type' => 'hierarchy',
                    'label' => 'Rootline',
                    'field' => 'rootline',
                    'sortBy' => 'count',
                ],
            ],
            'fake_solr_response_with_deep_more_then_10_hierarchy_facet_sorted_by_count.json',
        ];
        yield 'sortByIndex' => [
            [
                'pageHierarchy.' => [
                    'type' => 'hierarchy',
                    'label' => 'Rootline',
                    'field' => 'rootline',
                    'sortBy' => 'index',
                ],
            ],
            'fake_solr_response_with_deep_more_then_10_hierarchy_facet_sorted_by_index.json',
        ];
    }

    #[DataProvider('dataProviderForDeepMoreThen10DoesNotBreakHierarchyFacet')]
    #[Test]
    public function deepMoreThen10DoesNotBreakHierarchyFacet(array $facetConfiguration, string $fixtureFile): void
    {
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            $fixtureFile,
            $facetConfiguration,
        );

        /** @var HierarchyFacetParser $parser */
        $parser = $this->getInitializedParser(HierarchyFacetParser::class);
        $facet = $parser->parse($searchResultSet, 'pageHierarchy', $facetConfiguration['pageHierarchy.']);

        self::assertInstanceOf(HierarchyFacet::class, $facet);

        self::assertSame(1, $facet->getChildNodes()->getCount(), 'The hierarchy facet is broken. Expected that no node has more than one child node.');
        $this->assertNoNodeHasMoreThanOneChildInTheHierarchy($facet->getChildNodes()->getByPosition(0));
    }

    /**
     * Traverses the hierarchy facet and checks if some has more than one child.
     *
     * @param Node $node
     */
    protected function assertNoNodeHasMoreThanOneChildInTheHierarchy(Node $node)
    {
        self::assertLessThanOrEqual(1, $node->getChildNodes()->getCount(), 'The hierarchy facet is broken. Expected that no node has more than one child node.');
        if ($node->getChildNodes()->getCount() === 0) {
            return;
        }
        $this->assertNoNodeHasMoreThanOneChildInTheHierarchy($node->getChildNodes()->getByPosition(0));
    }

    #[Test]
    public function selectedOptionWithSlashInTitleOnHierarchicalFacetDoesNotBreakTheFacet(): void
    {
        $facetConfiguration = [
            'type' => 'hierarchy',
            'label' => 'Category Hierarch By Title',
            'field' => 'categoryHierarchyByTitle_stringM',
            'sortBy' => 'alpha',
            'keepAllOptionsOnSelection' => 1,
        ];
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_hierachy_facet_with_slash_in_title.json',
            $facetConfiguration,
            ['categoryHierarchyByTitle:/folder2\/level1\//folder2\/level2\//'],
        );

        /** @var HierarchyFacetParser $parser */
        $parser = $this->getInitializedParser(HierarchyFacetParser::class);
        /** @var HierarchyFacet $facet */
        $facet = $parser->parse($searchResultSet, 'categoryHierarchyByTitle', $facetConfiguration);
        // HierarchyFacetParser::getActiveFacetValuesFromRequest() must be aware about slashes in path segments
        self::assertSame(5, $facet->getAllFacetItems()->count(), 'Selected facet option is wrong parsed. The slash in Title leads to new facet option.');

        // each node has only one child node in fake response, parsing must be synchron with data.
        $this->assertNoNodeHasMoreThanOneChildInTheHierarchy($facet->getChildNodes()->getByPosition(0));

        // sub-options of facet
        $optionValue = '/folder2\/level1\//folder2\/level2\//folder2\/level3/';
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_hierachy_facet_with_slash_in_title.json',
            $facetConfiguration,
            ['categoryHierarchyByTitle:' . $optionValue],
        );
        $facet = $parser->parse($searchResultSet, 'categoryHierarchyByTitle', $facetConfiguration);

        /** @var Node $facetOption */
        $facetOption = $facet->getAllFacetItems()->getByValue($optionValue);
        self::assertSame(1, $facetOption->getChildNodes()->count(), 'Selected facet-option with slash in title/name breaks the Hierarchical facets.');
    }

    #[Test]
    public function facetIsNotActive(): void
    {
        $facetConfiguration = [
            'pageHierarchy.' => [
                'type' => 'hierarchy',
                'label' => 'Rootline',
                'field' => 'rootline',
            ],
        ];

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_hierarchy_facet.json',
            $facetConfiguration,
        );

        $parser = $this->getInitializedParser(HierarchyFacetParser::class);
        $facet = $parser->parse($searchResultSet, 'pageHierarchy', $facetConfiguration['pageHierarchy.']);
        self::assertFalse($facet->getIsUsed());
    }

    #[Test]
    public function facetIsActive(): void
    {
        $facetConfiguration = [
            'pageHierarchy.' => [
                'type' => 'hierarchy',
                'label' => 'Rootline',
                'field' => 'rootline',
            ],
        ];

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_hierarchy_facet.json',
            $facetConfiguration,
            ['pageHierarchy:/1/14/'],
        );

        /** @var HierarchyFacetParser $parser */
        $parser = $this->getInitializedParser(HierarchyFacetParser::class);
        /** @var HierarchyFacet $facet */
        $facet = $parser->parse($searchResultSet, 'pageHierarchy', $facetConfiguration['pageHierarchy.']);

        /** @var HierarchyFacet $selectedFacetByUrl */
        $selectedFacetByUrl = $facet->getChildNodes()->getByPosition(0)->getChildNodes()->getByPosition(0);
        self::assertTrue($facet->getIsUsed());

        $valueSelectedItem = $selectedFacetByUrl->getLabel();
        self::assertSame('14', $valueSelectedItem, 'Unexpected value for selected item');

        $subItemCountOfSelectedFacet = $selectedFacetByUrl->getChildNodes()->count();
        self::assertSame(15, $subItemCountOfSelectedFacet, 'Expected to have 15 sub items below path /1/14/');
    }
}
