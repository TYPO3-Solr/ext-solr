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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;

/**
 * Value object that represent a options facet.
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractFacet
{
    const TYPE_ABSTRACT = 'abstract';

    /**
     * String
     * @var string
     */
    protected static $type = self::TYPE_ABSTRACT;

    /**
     * The resultSet where this facet belongs to.
     *
     * @var SearchResultSet
     */
    protected $resultSet = null;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $field;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var boolean
     */
    protected $isAvailable = false;

    /**
     * @var bool
     */
    protected $isUsed = false;

    /**
     * @var bool
     */
    protected $allRequirementsMet = true;

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * AbstractFacet constructor.
     *
     * @param SearchResultSet $resultSet
     * @param string $name
     * @param string $field
     * @param string $label
     * @param array $configuration Facet configuration passed from typoscript
     */
    public function __construct(SearchResultSet $resultSet, $name, $field, $label = '', array $configuration = [])
    {
        $this->resultSet = $resultSet;
        $this->name = $name;
        $this->field = $field;
        $this->label = $label;
        $this->configuration = $configuration;
    }

    /**
     * Injects the object manager
     *
     * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(\TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get solr field name
     *
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param boolean $isAvailable
     */
    public function setIsAvailable($isAvailable)
    {
        $this->isAvailable = $isAvailable;
    }

    /**
     * @return boolean
     */
    public function getIsAvailable()
    {
        return $this->isAvailable;
    }

    /**
     * @param boolean $isUsed
     */
    public function setIsUsed($isUsed)
    {
        $this->isUsed = $isUsed;
    }

    /**
     * @return boolean
     */
    public function getIsUsed()
    {
        return $this->isUsed;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return static::$type;
    }

    /**
     * @return boolean
     */
    public function getAllRequirementsMet()
    {
        return $this->allRequirementsMet;
    }

    /**
     * @param boolean $allRequirementsMet
     */
    public function setAllRequirementsMet($allRequirementsMet)
    {
        $this->allRequirementsMet = $allRequirementsMet;
    }

    /**
     * @return SearchResultSet
     */
    public function getResultSet()
    {
        return $this->resultSet;
    }

    /**
     * Get configuration
     *
     * @return mixed
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * Get facet partial name used for rendering the facet
     *
     * @return string
     */
    public function getPartialName()
    {
        return 'Default';
    }

    /**
     * @return string
     */
    public function getGroupName()
    {
        return isset($this->configuration['groupName']) ? $this->configuration['groupName'] : 'main';
    }

    /**
     * Indicates if this facet should ne included in the available facets. When nothing is configured,
     * the method return TRUE.
     *
     * @return boolean
     */
    public function getIncludeInAvailableFacets()
    {
        return ((int)$this->getFacetSettingOrDefaultValue('includeInAvailableFacets', 1)) === 1;
    }

    /**
     * Indicates if this facets should be included in the used facets. When nothing is configured,
     * the methods returns true.
     *
     * @return boolean
     */
    public function getIncludeInUsedFacets()
    {

        return ((int)$this->getFacetSettingOrDefaultValue('includeInUsedFacets', 1)) === 1;
    }

    /**
     * Returns the configured requirements
     *
     * @return array
     */
    public function getRequirements()
    {
        return $this->getFacetSettingOrDefaultValue('requirements.', []);
    }

    /**
     * The implementation of this method should return a "flatten" collection of all items.
     *
     * @return AbstractFacetItemCollection
     */
    abstract public function getAllFacetItems();

    /**
     * @param string $key
     * @param mixed $defaultValue
     * @return mixed
     */
    protected function getFacetSettingOrDefaultValue($key, $defaultValue)
    {
        if (!isset($this->configuration[$key])) {
            return $defaultValue;
        }

        return ($this->configuration[$key]);
    }
}
