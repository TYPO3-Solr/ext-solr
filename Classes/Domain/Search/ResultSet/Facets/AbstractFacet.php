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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

/**
 * Value object that represent the options facet.
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
    protected static string $type = self::TYPE_ABSTRACT;

    /**
     * The resultSet where this facet belongs to.
     *
     * @var SearchResultSet
     */
    protected SearchResultSet $resultSet;

    /**
     * @var string
     */
    protected string $name;

    /**
     * @var string
     */
    protected string $field;

    /**
     * @var string
     */
    protected string $label;

    /**
     * @var array
     */
    protected array $configuration;

    /**
     * @var bool
     */
    protected bool $isAvailable = false;

    /**
     * @var bool
     */
    protected bool $isUsed = false;

    /**
     * @var bool
     */
    protected bool $allRequirementsMet = true;

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * AbstractFacet constructor.
     *
     * @param SearchResultSet $resultSet
     * @param string $name
     * @param string $field
     * @param string $label
     * @param array $configuration Facet configuration passed from typoscript
     */
    public function __construct(
        SearchResultSet $resultSet,
        string $name,
        string $field,
        string $label = '',
        array $configuration = []
    ) {
        $this->resultSet = $resultSet;
        $this->name = $name;
        $this->field = $field;
        $this->label = $label;
        $this->configuration = $configuration;
    }

    /**
     * Injects the object manager
     *
     * @param ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get solr field name
     *
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @param string $label
     */
    public function setLabel(string $label)
    {
        $this->label = $label;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param bool $isAvailable
     */
    public function setIsAvailable(bool $isAvailable)
    {
        $this->isAvailable = $isAvailable;
    }

    /**
     * @return bool
     */
    public function getIsAvailable(): bool
    {
        return $this->isAvailable;
    }

    /**
     * @param bool $isUsed
     */
    public function setIsUsed(bool $isUsed)
    {
        $this->isUsed = $isUsed;
    }

    /**
     * @return bool
     */
    public function getIsUsed(): bool
    {
        return $this->isUsed;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return static::$type;
    }

    /**
     * @return bool
     */
    public function getAllRequirementsMet(): bool
    {
        return $this->allRequirementsMet;
    }

    /**
     * @param bool $allRequirementsMet
     */
    public function setAllRequirementsMet(bool $allRequirementsMet)
    {
        $this->allRequirementsMet = $allRequirementsMet;
    }

    /**
     * @return SearchResultSet
     */
    public function getResultSet(): SearchResultSet
    {
        return $this->resultSet;
    }

    /**
     * Get configuration
     *
     * @return array
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * Get facet partial name used for rendering the facet
     *
     * @return string
     */
    public function getPartialName(): string
    {
        return 'Default';
    }

    /**
     * @return string
     */
    public function getGroupName(): string
    {
        return $this->configuration['groupName'] ?? 'main';
    }

    /**
     * Indicates if this facet should be included in the available facets. When nothing is configured,
     * the method return TRUE.
     *
     * @return bool
     */
    public function getIncludeInAvailableFacets(): bool
    {
        return ((int)$this->getFacetSettingOrDefaultValue('includeInAvailableFacets', 1)) === 1;
    }

    /**
     * Indicates if these facets should be included in the used facets. When nothing is configured,
     * the methods returns true.
     *
     * @return bool
     */
    public function getIncludeInUsedFacets(): bool
    {
        return ((int)$this->getFacetSettingOrDefaultValue('includeInUsedFacets', 1)) === 1;
    }

    /**
     * Returns the configured requirements
     *
     * @return array
     */
    public function getRequirements(): array
    {
        return $this->getFacetSettingOrDefaultValue('requirements.', []);
    }

    /**
     * The implementation of this method should return a "flatten" collection of all items.
     *
     * @return AbstractFacetItemCollection
     */
    abstract public function getAllFacetItems(): AbstractFacetItemCollection;

    /**
     * @param string $key
     * @param mixed $defaultValue
     * @return mixed
     */
    protected function getFacetSettingOrDefaultValue(string $key, $defaultValue)
    {
        if (!isset($this->configuration[$key])) {
            return $defaultValue;
        }

        return $this->configuration[$key];
    }
}
