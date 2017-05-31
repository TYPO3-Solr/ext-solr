<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets;

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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetParserInterface;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetParserRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacetParser;
use TYPO3\CMS\Core\Tests\UnitTestCase;

/**
 * Testcases for the Facet parser registry
 *
 * @author Frans Saris <frans@beech.it>
 */
class FacetParserRegistryTest extends UnitTestCase
{
    /**
     * Initialize a RendererRegistry and mock createRendererInstance()
     *
     * @param array $createsParserInstances
     * @return \PHPUnit_Framework_MockObject_MockObject|FacetParserRegistry
     */
    protected function getTestFacetParserRegistry(array $createsParserInstances = [])
    {
        $facetParserRegistry = $this->getMockBuilder(FacetParserRegistry::class)
            ->setMethods(['createParserInstance'])
            ->getMock();

        if (!empty($createsParserInstances)) {
            $facetParserRegistry->expects($this->any())
                ->method('createParserInstance')
                ->will($this->returnValueMap($createsParserInstances));
        }

        return $facetParserRegistry;
    }

    /**
     * @test
     */
    public function registeredParserClassCanBeRetrievedByType()
    {
        $facetType = 'myType';
        $parserClass = $this->getUniqueId('myParser');
        $parserObject = $this->getMockBuilder(FacetParserInterface::class)->setMockClassName($parserClass)->getMock();

        $facetParserRegistry = $this->getTestFacetParserRegistry([[$parserClass, $parserObject]]);

        $facetParserRegistry->registerParser($parserClass, $facetType);

        $this->assertEquals($parserObject, $facetParserRegistry->getParser($facetType));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 1462883324
     */
    public function registerParserClassThrowsExceptionIfClassDoesNotExist()
    {
        $facetParserRegistry = $this->getTestFacetParserRegistry();
        $facetParserRegistry->registerParser($this->getUniqueId(), 'unknown');
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 1462883325
     */
    public function registerParserClassThrowsExceptionIfClassDoesNotImplementFacetParserInterface()
    {
        $className = __CLASS__;
        $facetParserRegistry = $this->getTestFacetParserRegistry();
        $facetParserRegistry->registerParser($className, 'unknown');
    }

    /**
     * @test
     */
    public function registerReturnsDefaultParserForUnknownFacetType()
    {
        $optionsFacetParser = new OptionsFacetParser();
        $facetParserRegistry = $this->getTestFacetParserRegistry([[OptionsFacetParser::class, $optionsFacetParser]]);
        $this->assertEquals($optionsFacetParser, $facetParserRegistry->getParser('unknownType'));
    }

    /**
     * @test
     */
    public function canRegisterDifferentDefaultParser()
    {
        $parserClass = $this->getUniqueId('myParser');
        $parserObject = $this->getMockBuilder(FacetParserInterface::class)->setMockClassName($parserClass)->getMock();


        $facetParserRegistry = $this->getTestFacetParserRegistry([[$parserClass, $parserObject]]);
        $facetParserRegistry->setDefaultParser($parserClass);

        $this->assertEquals($parserObject, $facetParserRegistry->getParser('unknownType'));
    }
}
