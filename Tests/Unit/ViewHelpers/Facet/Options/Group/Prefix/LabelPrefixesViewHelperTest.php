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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelpers\Facet\Options\Group\Prefix;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\OptionCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use ApacheSolrForTypo3\Solr\ViewHelpers\Facet\Options\Group\Prefix\LabelPrefixesViewHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

class LabelPrefixesViewHelperTest extends SetUpUnitTestCase
{
    #[Test]
    public function canGetPrefixesSortedByOrderInCollection(): void
    {
        $testArguments['length'] = 1;

        $testableStack = $this->getTestableStack($testArguments);
        extract($testableStack, EXTR_OVERWRITE);
        /**
         * extracted:
         * @var LabelPrefixesViewHelper $testable
         * @var StandardVariableProvider $variableContainer
         * @var RenderingContextInterface $renderingContextMock
         */
        $testable->render();

        self::assertTrue($variableContainer->exists('prefixes'), 'Expected that prefixes has been set');
        $prefixes = $variableContainer->get('prefixes');
        self::assertSame(['r', 'p', 'l'], $prefixes, 'ViewHelper registers unexpected prefixes from passed options');
    }

    #[Test]
    public function canGetPrefixesSortedAlphabeticalByLabel(): void
    {
        $testArguments['length'] = 1;
        $testArguments['sortBy'] = 'alpha';

        $testableStack = $this->getTestableStack($testArguments);
        extract($testableStack, EXTR_OVERWRITE);
        /**
         * extracted:
         * @var LabelPrefixesViewHelper $testable
         * @var StandardVariableProvider $variableContainer
         * @var RenderingContextInterface $renderingContextMock
         */
        $testable->render();

        $prefixes = $variableContainer->get('prefixes');
        self::assertSame(['l', 'p', 'r'], $prefixes, 'ViewHelper registers unexpected prefixes from passed options');
    }

    protected function getTestFacetOptionCollection(): OptionCollection
    {
        $facet = $this->createMock(OptionsFacet::class);

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

    protected function getTestableStack(
        array &$testArguments,
    ): array {
        $testArguments['options'] = $this->getTestFacetOptionCollection();

        $variableContainer = $this->getMockBuilder(StandardVariableProvider::class)->onlyMethods(['remove'])->getMock();
        $renderingContextMock = $this->createMock(RenderingContextInterface::class);
        $renderingContextMock->expects(self::any())->method('getVariableProvider')->willReturn($variableContainer);

        $labelPrefixesViewHelperTestable = new LabelPrefixesViewHelper();
        $labelPrefixesViewHelperTestable->setRenderingContext($renderingContextMock);
        $labelPrefixesViewHelperTestable->setArguments($testArguments);
        $labelPrefixesViewHelperTestable->setViewHelperNode($this->createMock(ViewHelperNode::class));

        return [
            'testable' => $labelPrefixesViewHelperTestable,
            'variableContainer' => $variableContainer,
            'renderingContextMock' => $renderingContextMock,
        ];
    }
}
