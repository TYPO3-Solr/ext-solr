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

/**
 * Value object that represent the options facet.
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractFacet
{
    public const TYPE_ABSTRACT = 'abstract';

    protected static string $type = self::TYPE_ABSTRACT;

    protected bool $isAvailable = false;

    protected bool $isUsed = false;

    protected bool $allRequirementsMet = true;

    public function __construct(
        protected SearchResultSet $resultSet,
        protected string $name,
        protected string $field,
        protected string $label = '',
        protected array $facetConfiguration = []
    ) {
    }

    /**
     * Returns facet name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns Solr-Document's field name
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Sets the label for facet
     */
    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * Returns the label of facet
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Sets the "available" property of facet
     */
    public function setIsAvailable(bool $isAvailable): void
    {
        $this->isAvailable = $isAvailable;
    }

    /**
     * Returns the "available" property of facet
     */
    public function getIsAvailable(): bool
    {
        return $this->isAvailable;
    }

    /**
     * Sets the "used" property of facet
     */
    public function setIsUsed(bool $isUsed): void
    {
        $this->isUsed = $isUsed;
    }

    /**
     * Returns the "used" property of facet
     */
    public function getIsUsed(): bool
    {
        return $this->isUsed;
    }

    /**
     * Returns the type of facet
     */
    public function getType(): string
    {
        return static::$type;
    }

    /**
     * Returns the "allRequirementsMet" property of facet
     */
    public function getAllRequirementsMet(): bool
    {
        return $this->allRequirementsMet;
    }

    /**
     * Sets the "allRequirementsMet" property of facet
     */
    public function setAllRequirementsMet(bool $allRequirementsMet): void
    {
        $this->allRequirementsMet = $allRequirementsMet;
    }

    /**
     * Returns {@link SearchResultSet}
     */
    public function getResultSet(): SearchResultSet
    {
        return $this->resultSet;
    }

    /**
     * Get configuration array of facet
     */
    public function getConfiguration(): array
    {
        return $this->facetConfiguration;
    }

    /**
     * Get facet partial name used for rendering the facet
     */
    public function getPartialName(): string
    {
        return 'Default';
    }

    /**
     * Returns the "groupName" the facet belongs to.
     * Is defined via "groupName" property in facet configuration
     */
    public function getGroupName(): string
    {
        return $this->facetConfiguration['groupName'] ?? 'main';
    }

    /**
     * Indicates if this facet should be included in the available facets. When nothing is configured,
     * the method return TRUE.
     */
    public function getIncludeInAvailableFacets(): bool
    {
        return ((int)$this->getFacetSettingOrDefaultValue('includeInAvailableFacets', 1)) === 1;
    }

    /**
     * Indicates if these facets should be included in the used facets. When nothing is configured,
     * the methods returns true.
     */
    public function getIncludeInUsedFacets(): bool
    {
        return ((int)$this->getFacetSettingOrDefaultValue('includeInUsedFacets', 1)) === 1;
    }

    /**
     * Returns the configured requirements
     */
    public function getRequirements(): array
    {
        return $this->getFacetSettingOrDefaultValue('requirements.', []);
    }

    /**
     * The implementation of this method should return a "flatten" collection of all items.
     */
    abstract public function getAllFacetItems(): AbstractFacetItemCollection;

    /**
     * Returns the facets setting/property value or default value.
     */
    protected function getFacetSettingOrDefaultValue(string $key, mixed $defaultValue): mixed
    {
        if (!isset($this->facetConfiguration[$key])) {
            return $defaultValue;
        }

        return $this->facetConfiguration[$key];
    }
}
