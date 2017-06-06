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

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

/**
 * Class FacetParserRegistry
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets
 */
class FacetParserRegistry implements SingletonInterface
{
    /**
     * Array of available parser classNames
     *
     * @var array
     */
    protected $parsers = [
        'options' => OptionBased\Options\OptionsFacetParser::class,
        'hierarchy' => OptionBased\Hierarchy\HierarchyFacetParser::class,
        'queryGroup' => OptionBased\QueryGroup\QueryGroupFacetParser::class,
        'dateRange' => RangeBased\DateRange\DateRangeFacetParser::class,
        'numericRange' => RangeBased\NumericRange\NumericRangeFacetParser::class,
    ];
    /**
     * Default parser className
     *
     * @var string
     */
    protected $defaultParser = OptionBased\Options\OptionsFacetParser::class;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Get defaultParser
     *
     * @return string
     */
    public function getDefaultParser()
    {
        return $this->defaultParser;
    }

    /**
     * Set defaultParser
     *
     * @param string $defaultParserClassName
     */
    public function setDefaultParser($defaultParserClassName)
    {
        $this->defaultParser = $defaultParserClassName;
    }

    /**
     * Get registered parser classNames
     *
     * @return array
     */
    public function getParsers()
    {
        return $this->parsers;
    }

    /**
     * @param string $className
     * @param string $type
     * @throws \InvalidArgumentException
     */
    public function registerParser($className, $type)
    {

        // check if the class is available for TYPO3 before registering the driver
        if (!class_exists($className)) {
            throw new \InvalidArgumentException('Class ' . $className . ' does not exist.', 1462883324);
        }

        if (!in_array(FacetParserInterface::class, class_implements($className), true)) {
            throw new \InvalidArgumentException('Parser ' . $className . ' needs to implement the FacetParserInterface.', 1462883325);
        }

        $this->parsers[$type] = $className;
    }

    /**
     * Get parser
     *
     * @param string $type
     * @return FacetParserInterface
     */
    public function getParser($type)
    {
        $className = $this->defaultParser;

        if (isset($this->parsers[$type])) {
            $className = $this->parsers[$type];
        }

        return $this->createParserInstance($className);
    }

    /**
     * Create an instance of a certain parser class
     *
     * @param string $className
     * @return FacetParserInterface
     */
    protected function createParserInstance($className)
    {
        /** @var $instance FacetParserInterface */
        $instance = $this->objectManager->get($className);
        return $instance;
    }
}
