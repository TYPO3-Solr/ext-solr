<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\HierarchyPackage;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsPackage;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup\QueryGroupPackage;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange\DateRangePackage;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange\NumericRangePackage;
use ApacheSolrForTypo3\Solr\System\Object\AbstractClassRegistry;

/**
 * Class FacetRegistry
 */
class FacetRegistry extends AbstractClassRegistry
{
    /**
     * Array of available parser classNames
     */
    protected array $classMap = [
        'options' => OptionsPackage::class,
        'hierarchy' => HierarchyPackage::class,
        'queryGroup' => QueryGroupPackage::class,
        'dateRange' => DateRangePackage::class,
        'numericRange' => NumericRangePackage::class,
    ];

    /**
     * Default parser className
     */
    protected string $defaultClass = OptionsPackage::class;

    /**
     * Get defaultParser
     */
    public function getDefaultPackage(): string
    {
        return $this->defaultClass;
    }

    /**
     * Set defaultParser
     */
    public function setDefaultPackage(string $defaultPackageClassName): void
    {
        $this->defaultClass = $defaultPackageClassName;
    }

    /**
     * Get registered parser classNames
     */
    public function getPackages(): array
    {
        return $this->classMap;
    }

    /**
     * Registers package
     */
    public function registerPackage(string $className, string $type): void
    {
        $this->register($className, $type, AbstractFacetPackage::class);
    }

    /**
     * Returns facet package object
     *
     * @throws InvalidFacetPackageException
     */
    public function getPackage(string $type): AbstractFacetPackage
    {
        $instance = $this->getInstance($type);
        if (!$instance instanceof AbstractFacetPackage) {
            throw new InvalidFacetPackageException(
                'Invalid class registered for ' . htmlspecialchars($type),
                2019898766,
            );
        }
        return $instance;
    }
}
