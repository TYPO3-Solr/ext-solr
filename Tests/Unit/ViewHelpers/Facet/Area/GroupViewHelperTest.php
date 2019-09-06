<?php
namespace ApacheSolrForTypo3\Solr\Test\ViewHelpers\Facet\Area;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\ViewHelpers\Facet\Area\GroupViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class GroupViewHelperTest extends UnitTest
{
    /**
     * @test
     */
    public function canMakeOnlyExpectedFacetsAvailableInStaticContext()
    {
        $facetCollection = $this->getTestFacetCollection();

        $variableContainer = $this->getMockBuilder(StandardVariableProvider::class)->setMethods(['remove'])->getMock();
        $renderingContextMock = $this->getDumbMock(RenderingContextInterface::class);
        $renderingContextMock->expects($this->any())->method('getVariableProvider')->will($this->returnValue($variableContainer));

        $testArguments['facets'] = $facetCollection;
        $testArguments['groupName'] = 'left';

        GroupViewHelper::renderStatic($testArguments, function () {}, $renderingContextMock);
        $this->assertTrue($variableContainer->exists('areaFacets'), 'Expected that filteredFacets has been set');

            /** @var  $facetCollection FacetCollection */
        $facetCollection = $variableContainer->get('areaFacets');
        $this->assertEquals(2, $facetCollection->getCount());

        $facetKeys = array_keys($facetCollection->getArrayCopy());
        $this->assertEquals(['color', 'brand'], $facetKeys);
    }


    /**
     * @test
     */
    public function canMakeOnlyExpectedFacetsAvailableInstanceContext()
    {
        $facetCollection = $this->getTestFacetCollection();

        $variableContainer = $this->getMockBuilder(StandardVariableProvider::class)->setMethods(['remove'])->getMock();
        $renderingContextMock = $this->getDumbMock(RenderingContextInterface::class);
        $renderingContextMock->expects($this->any())->method('getVariableProvider')->will($this->returnValue($variableContainer));

        $viewHelper = $this->getMockBuilder(GroupViewHelper::class)->setMethods(['renderChildren'])->getMock();
        $viewHelper->setRenderingContext($renderingContextMock);
        $viewHelper->setArguments(['facets' => $facetCollection, 'groupName' => 'left']);
        $viewHelper->render();

        $this->assertTrue($variableContainer->exists('areaFacets'), 'Expected that filteredFacets has been set');

        /** @var  $facetCollection FacetCollection */
        $facetCollection = $variableContainer->get('areaFacets');
        $this->assertEquals(2, $facetCollection->getCount());

        $facetKeys = array_keys($facetCollection->getArrayCopy());
        $this->assertEquals(['color', 'brand'], $facetKeys);
    }

    /**
     * @return FacetCollection
     */
    protected function getTestFacetCollection()
    {
        $facetCollection = new FacetCollection();
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);

        $colorFacet = new OptionsFacet($resultSetMock, 'color', 'color_s', '', ['groupName' => 'left']);
        $brandFacet = new OptionsFacet($resultSetMock, 'brand', 'brand_s', '', ['groupName' => 'left']);
        $pageType = new OptionsFacet($resultSetMock, 'type', 'type', '', ['groupName' => 'top']);

        $facetCollection->addFacet($colorFacet);
        $facetCollection->addFacet($brandFacet);
        $facetCollection->addFacet($pageType);
        return $facetCollection;
    }
}
