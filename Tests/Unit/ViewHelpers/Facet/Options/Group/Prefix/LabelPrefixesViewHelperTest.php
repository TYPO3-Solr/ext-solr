<?php
namespace ApacheSolrForTypo3\Solr\Test\ViewHelpers\Facet\Options\Group\Prefix;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\OptionCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\ViewHelpers\Facet\Options\Group\Prefix\LabelPrefixesViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class LabelPrefixesViewHelperTest extends UnitTest
{
    /**
     * @test
     */
    public function canGetPrefixesSortedByOrderInCollection()
    {
        $optionCollection = $this->getTestFacetOptionCollection();

        $variableContainer = $this->getMockBuilder(StandardVariableProvider::class)->setMethods(['remove'])->getMock();
        $renderingContextMock = $this->getDumbMock(RenderingContextInterface::class);
        $renderingContextMock->expects($this->any())->method('getVariableProvider')->will($this->returnValue($variableContainer));

        $testArguments['options'] = $optionCollection;
        $testArguments['length'] = 1;
        LabelPrefixesViewHelper::renderStatic($testArguments, function () {}, $renderingContextMock);
        $this->assertTrue($variableContainer->exists('prefixes'), 'Expected that prefixes has been set');
        $prefixes = $variableContainer->get('prefixes');
        $this->assertSame(['r','p','l'], $prefixes, 'ViewHelper registers unexpected prefixes from passed options');
    }

    /**
     * @test
     */
    public function canGetPrefixesSortedAlphabeticalByLabel()
    {
        $optionCollection = $this->getTestFacetOptionCollection();

        $variableContainer = $this->getMockBuilder(StandardVariableProvider::class)->setMethods(['remove'])->getMock();
        $renderingContextMock = $this->getDumbMock(RenderingContextInterface::class);
        $renderingContextMock->expects($this->any())->method('getVariableProvider')->will($this->returnValue($variableContainer));

        $testArguments['options'] = $optionCollection;
        $testArguments['length'] = 1;
        $testArguments['sortBy'] = 'alpha';
        LabelPrefixesViewHelper::renderStatic($testArguments, function () {}, $renderingContextMock);
        $prefixes = $variableContainer->get('prefixes');
        $this->assertSame(['l','p','r'], $prefixes, 'ViewHelper registers unexpected prefixes from passed options');
    }

    /**
     * @return OptionCollection
     */
    protected function getTestFacetOptionCollection(): OptionCollection
    {
        $facet = $this->getDumbMock(OptionsFacet::class);

        $roseRed = new Option($facet, 'Rose Red', 'rose_red', 14);
        $blue = new Option($facet, 'Polar Blue', 'polar_blue', 12);
        $yellow = new Option($facet, 'Lemon Yellow', 'lemon_yellow', 3);
        $red = new Option($facet, 'Rubin Red', 'rubin_red', 2);
        $royalGreen = new Option($facet, 'Royal Green', 'royal_green', 1);

        $optionCollection = new OptionCollection();
        $optionCollection->add($roseRed);
        $optionCollection->add($blue);
        $optionCollection->add($yellow);
        $optionCollection->add($red);
        $optionCollection->add($royalGreen);
        return $optionCollection;
    }
}
