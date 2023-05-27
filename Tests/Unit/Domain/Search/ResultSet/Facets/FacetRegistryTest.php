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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsPackage;
use ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\TestPackage\TestPackage;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Testcases for the Facet parser registry
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class FacetRegistryTest extends SetUpUnitTestCase
{
    /**
     * Initialize a RendererRegistry and mock createRendererInstance()
     */
    protected function getTestFacetPackageRegistry(array $createsPackageInstances = []): MockObject|FacetRegistry
    {
        $facetRegistry = $this->getMockBuilder(FacetRegistry::class)
            ->onlyMethods(['createInstance'])
            ->getMock();

        if (!empty($createsPackageInstances)) {
            $facetRegistry->expects(self::any())
                ->method('createInstance')
                ->willReturnMap($createsPackageInstances);
        }

        return $facetRegistry;
    }

    /**
     * @test
     */
    public function registeredPackageClassCanBeRetrievedByType(): void
    {
        $facetType = 'myType';
        $packageObject = new TestPackage();
        $packageClass = get_class($packageObject);

        $facetRegistry = $this->getTestFacetPackageRegistry([[$packageClass, $packageObject]]);
        $facetRegistry->registerPackage($packageClass, $facetType);

        self::assertEquals($packageObject, $facetRegistry->getPackage($facetType));
    }

    /**
     * @test
     */
    public function registerParserClassThrowsExceptionIfClassDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1462883324);
        $facetParserRegistry = $this->getTestFacetPackageRegistry();
        $facetParserRegistry->registerPackage($this->getUniqueId(), 'unknown');
    }

    /**
     * @test
     */
    public function registerParserClassThrowsExceptionIfClassDoesNotImplementFacetPackageInterface(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1462883325);
        $className = __CLASS__;
        $facetParserRegistry = $this->getTestFacetPackageRegistry();
        $facetParserRegistry->registerPackage($className, 'unknown');
    }

    /**
     * @test
     */
    public function registerReturnsDefaultPackageForUnknownFacetType(): void
    {
        $optionsFacetPackage = new OptionsPackage();
        $facetParserRegistry = $this->getTestFacetPackageRegistry([[OptionsPackage::class, $optionsFacetPackage]]);
        self::assertEquals($optionsFacetPackage, $facetParserRegistry->getPackage('unknownType'));
    }

    /**
     * @test
     */
    public function canRegisterDifferentDefaultPackage(): void
    {
        $packageObject = new TestPackage();
        $packageClass = get_class($packageObject);

        $facetParserRegistry = $this->getTestFacetPackageRegistry([[$packageClass, $packageObject]]);
        $facetParserRegistry->setDefaultPackage($packageClass);

        self::assertEquals($packageObject, $facetParserRegistry->getPackage('unknownType'));
    }
}
