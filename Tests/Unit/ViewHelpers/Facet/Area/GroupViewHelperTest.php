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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelpers\Facet\Area;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use ApacheSolrForTypo3\Solr\ViewHelpers\Facet\Area\GroupViewHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

class GroupViewHelperTest extends SetUpUnitTestCase
{
    /**
     * @throws MockObjectException
     */
    #[Test]
    public function canMakeOnlyExpectedFacetsAvailableInStaticContext(): void
    {
        $facetCollection = $this->getTestFacetCollection();

        $variableContainer = $this->getMockBuilder(StandardVariableProvider::class)
            ->onlyMethods([
                'remove',
            ])
            ->getMock();
        $renderingContextMock = $this->createMock(RenderingContextInterface::class);
        $renderingContextMock
            ->expects(self::any())
            ->method('getVariableProvider')
            ->willReturn($variableContainer);

        $testArguments['facets'] = $facetCollection;
        $testArguments['groupName'] = 'left';

        $groupViewHelperTestable = new GroupViewHelper();
        $groupViewHelperTestable->setRenderingContext($renderingContextMock);
        $viewHelperNodeMock = $this->createMock(ViewHelperNode::class);
        $groupViewHelperTestable->setViewHelperNode($viewHelperNodeMock);
        $groupViewHelperTestable->setArguments($testArguments);
        $groupViewHelperTestable->render();
        self::assertTrue($variableContainer->exists('areaFacets'), 'Expected that filteredFacets has been set');

        /** @var FacetCollection $facetCollection */
        $facetCollection = $variableContainer->get('areaFacets');
        self::assertEquals(2, $facetCollection->getCount());

        $facetKeys = array_keys($facetCollection->getArrayCopy());
        self::assertEquals(['color', 'brand'], $facetKeys);
    }

    /**
     * @throws MockObjectException
     */
    #[Test]
    public function canMakeOnlyExpectedFacetsAvailableInstanceContext(): void
    {
        $facetCollection = $this->getTestFacetCollection();

        $variableContainer = $this->getMockBuilder(StandardVariableProvider::class)
            ->onlyMethods([
                'remove',
            ])
            ->getMock();
        $renderingContextMock = $this->createMock(RenderingContextInterface::class);
        $renderingContextMock
            ->expects(self::any())
            ->method('getVariableProvider')
            ->willReturn($variableContainer);

        $viewHelper = $this->getMockBuilder(GroupViewHelper::class)
            ->onlyMethods([
                'renderChildren',
            ])
            ->getMock();
        $viewHelper->setRenderingContext($renderingContextMock);
        $viewHelperNodeMock = $this->createMock(ViewHelperNode::class);
        $viewHelper->setViewHelperNode($viewHelperNodeMock);
        $viewHelper->setArguments(['facets' => $facetCollection, 'groupName' => 'left']);
        $viewHelper->render();

        self::assertTrue($variableContainer->exists('areaFacets'), 'Expected that filteredFacets has been set');

        /** @var FacetCollection $facetCollection */
        $facetCollection = $variableContainer->get('areaFacets');
        self::assertEquals(2, $facetCollection->getCount());

        $facetKeys = array_keys($facetCollection->getArrayCopy());
        self::assertEquals(['color', 'brand'], $facetKeys);
    }

    /**
     * @throws MockObjectException
     */
    protected function getTestFacetCollection(): FacetCollection
    {
        $facetCollection = new FacetCollection();
        $resultSetMock = $this->createMock(SearchResultSet::class);

        $colorFacet = new OptionsFacet($resultSetMock, 'color', 'color_s', '', ['groupName' => 'left']);
        $brandFacet = new OptionsFacet($resultSetMock, 'brand', 'brand_s', '', ['groupName' => 'left']);
        $pageType = new OptionsFacet($resultSetMock, 'type', 'type', '', ['groupName' => 'top']);

        $facetCollection->addFacet($colorFacet);
        $facetCollection->addFacet($brandFacet);
        $facetCollection->addFacet($pageType);
        return $facetCollection;
    }
}
