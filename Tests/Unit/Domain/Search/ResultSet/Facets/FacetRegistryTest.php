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

use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsPackage;
use ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\TestPackage\TestPackage;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

/**
 * Testcases for the Facet parser registry
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class FacetRegistryTest extends UnitTest
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManagerMock;

    /**
     * @return void
     */
    public function setUp() {
        parent::setUp();
        $this->objectManagerMock = $this->getMockBuilder(ObjectManager::class)->disableOriginalConstructor()->getMock();
    }

    /**
     * Initialize a RendererRegistry and mock createRendererInstance()
     *
     * @param array $createsParserInstances
     * @return \PHPUnit_Framework_MockObject_MockObject|FacetRegistry
     */
    protected function getTestFacetPackageRegistry(array $createsPackageInstances = [])
    {
        /** @var $facetRegistry FacetRegistry */
        $facetRegistry = $this->getMockBuilder(FacetRegistry::class)
            ->setMethods(['createInstance'])
            ->getMock();

        // @extensionScannerIgnoreLine
        $facetRegistry->injectObjectManager($this->objectManagerMock);

        if (!empty($createsPackageInstances)) {
            $facetRegistry->expects($this->any())
                ->method('createInstance')
                ->will($this->returnValueMap($createsPackageInstances));
        }

        return $facetRegistry;
    }

    /**
     * @test
     */
    public function registeredPackageClassCanBeRetrievedByType()
    {
        $facetType = 'myType';
        $packageObject = new TestPackage();
        $packageClass = get_class($packageObject);

        $facetRegistry = $this->getTestFacetPackageRegistry([[$packageClass, $packageObject]]);
        $facetRegistry->registerPackage($packageClass, $facetType);

        $this->assertEquals($packageObject, $facetRegistry->getPackage($facetType));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 1462883324
     */
    public function registerParserClassThrowsExceptionIfClassDoesNotExist()
    {
        $facetParserRegistry = $this->getTestFacetPackageRegistry();
        $facetParserRegistry->registerPackage($this->getUniqueId(), 'unknown');
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionCode 1462883325
     */
    public function registerParserClassThrowsExceptionIfClassDoesNotImplementFacetPackageInterface()
    {
        $className = __CLASS__;
        $facetParserRegistry = $this->getTestFacetPackageRegistry();
        $facetParserRegistry->registerPackage($className, 'unknown');
    }

    /**
     * @test
     */
    public function registerReturnsDefaultPackageForUnknownFacetType()
    {
        $optionsFacetPackage = new OptionsPackage();
        $facetParserRegistry = $this->getTestFacetPackageRegistry([[OptionsPackage::class, $optionsFacetPackage]]);
        $this->assertEquals($optionsFacetPackage, $facetParserRegistry->getPackage('unknownType'));
    }

    /**
     * @test
     */
    public function canRegisterDifferentDefaultPackage()
    {
        $packageObject = new TestPackage();
        $packageClass = get_class($packageObject);

        $facetParserRegistry = $this->getTestFacetPackageRegistry([[$packageClass, $packageObject]]);
        $facetParserRegistry->setDefaultPackage($packageClass);

        $this->assertEquals($packageObject, $facetParserRegistry->getPackage('unknownType'));
    }
}
