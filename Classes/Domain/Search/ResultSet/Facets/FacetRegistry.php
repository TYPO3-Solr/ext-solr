<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\HierarchyPackage;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsPackage;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup\QueryGroupPackage;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange\DateRangePackage;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange\NumericRangePackage;
use ApacheSolrForTypo3\Solr\System\Object\AbstractClassRegistry;

/**
 * Class FacetRegistry
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class FacetRegistry extends AbstractClassRegistry
{
    /**
     * Array of available parser classNames
     *
     * @var array
     */
    protected $classMap = [
        'options' => OptionsPackage::class,
        'hierarchy' => HierarchyPackage::class,
        'queryGroup' => QueryGroupPackage::class,
        'dateRange' => DateRangePackage::class,
        'numericRange' => NumericRangePackage::class,
    ];

    /**
     * Default parser className
     *
     * @var string
     */
    protected $defaultClass = OptionsPackage::class;

    /**
     * Get defaultParser
     *
     * @return string
     */
    public function getDefaultPackage()
    {
        return $this->defaultClass;
    }

    /**
     * Set defaultParser
     *
     * @param string $defaultPackageClassName
     */
    public function setDefaultPackage($defaultPackageClassName)
    {
        $this->defaultClass = $defaultPackageClassName;
    }

    /**
     * Get registered parser classNames
     *
     * @return array
     */
    public function getPackages()
    {
        return $this->classMap;
    }

    /**
     * @param string $className
     * @param string $type
     * @throws \InvalidArgumentException
     */
    public function registerPackage($className, $type)
    {
        return $this->register($className, $type, AbstractFacetPackage::class);
    }

    /**
     * Get package
     *
     * @param string $type
     * @return AbstractFacetPackage
     * @throws InvalidFacetPackageException
     */
    public function getPackage($type)
    {
        $instance = $this->getInstance($type);
        if (!$instance instanceof AbstractFacetPackage) {
            throw new InvalidFacetPackageException('Invalid class registered for ' . htmlspecialchars($type));
        }
        $instance->setObjectManager($this->objectManager);
        return $instance;
    }
}
