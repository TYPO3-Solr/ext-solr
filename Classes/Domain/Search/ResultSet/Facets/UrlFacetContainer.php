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

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Util\ArrayAccessor;
use ApacheSolrForTypo3\Solr\Utility\ParameterSortingUtility;

/**
 * Data bag for facets inside of an url
 *
 * @author Lars Tode <lars.tode@dkd.de>
 * @api
 */
class UrlFacetContainer implements \Countable
{
    /**
     * Parameters array has a numeric index
     */
    const PARAMETER_STYLE_INDEX = 'index';

    /**
     * Parameters array uses combination of key and value as index
     */
    const PARAMETER_STYLE_ASSOC = 'assoc';

    /**
     * Used parameter style
     *
     * @var string
     */
    protected $parameterStyle = self::PARAMETER_STYLE_INDEX;

    /**
     * Argument namespace as configures in TypoScript
     *
     * @var string
     */
    protected $argumentNameSpace = 'tx_solr';

    /**
     * @var ArrayAccessor
     */
    protected $argumentsAccessor;

    /**
     * Mark the data bag as changed
     *
     * @var bool
     */
    protected $changed = false;

    /**
     * Parameters should be sorted
     *
     * @var bool
     */
    protected $sort = false;

    /**
     * UrlFacetConstructor constructor.
     *
     * @param ArrayAccessor $argumentsAccessor
     * @param string $argumentNameSpace
     * @param string $parameterStyle
     */
    public function __construct(
        ArrayAccessor $argumentsAccessor,
        string $argumentNameSpace = SearchRequest::DEFAULT_PLUGIN_NAMESPACE,
        string $parameterStyle = self::PARAMETER_STYLE_INDEX
    ) {
        // Take care that the url style matches in case and is one of the allowed values
        $parameterStyle = strtolower(trim($parameterStyle));
        if (empty($parameterStyle) ||
            (!in_array($parameterStyle, [self::PARAMETER_STYLE_INDEX, self::PARAMETER_STYLE_ASSOC]))) {
            $parameterStyle = self::PARAMETER_STYLE_INDEX;
        }
        $this->argumentsAccessor = $argumentsAccessor;
        $this->argumentNameSpace = $argumentNameSpace;
        $this->parameterStyle = $parameterStyle;

        if ($this->parameterStyle === self::PARAMETER_STYLE_ASSOC) {
            $this->sort = true;
        }
    }

    /**
     * Enable the sort of URL parameters
     *
     * @return $this
     */
    public function enableSort(): self
    {
        $this->sort = true;

        return $this;
    }

    /**
     * Disable the sort of URL parameters
     *
     * @return $this
     */
    public function disableSort(): self
    {
        // If the parameter style is assoc, all parameters have to be sorted!
        if ($this->parameterStyle === self::PARAMETER_STYLE_INDEX) {
            $this->sort = false;
        }

        return $this;
    }

    /**
     * Returns the information if the parameters are sorted
     *
     * @return bool
     */
    public function isSorted(): bool
    {
        return $this->sort;
    }

    /**
     * @return string
     */
    public function getParameterStyle(): string
    {
        return $this->parameterStyle;
    }

    /**
     * Helper method to prefix an accessor with the arguments namespace.
     *
     * @param string $path
     * @return string
     */
    protected function prefixWithNamespace(string $path = 'filter'): string
    {
        return $this->argumentNameSpace . ':' . $path;
    }

    /**
     * Returns the list of activate facet names
     *
     * @return array
     */
    public function getActiveFacetNames(): array
    {
        $activeFacets = $this->getActiveFacets();
        $facetNames = [];

        if ($this->parameterStyle === self::PARAMETER_STYLE_INDEX) {
            array_map(function($activeFacet) use (&$facetNames) {
                $facetNames[] = substr($activeFacet, 0, strpos($activeFacet, ':'));
            }, $activeFacets);
        } else {
            array_map(function($activeFacet) use (&$facetNames) {
                $facetNames[] = substr($activeFacet, 0, strpos($activeFacet, ':'));
            }, array_keys($activeFacets));
        }

        return $facetNames;
    }

    /**
     * Returns all facet values for a certain facetName
     * @param string $facetName
     * @return array
     */
    public function getActiveFacetValuesByName(string $facetName): array
    {
        $values = [];
        $activeFacets = $this->getActiveFacets();
        if ($this->parameterStyle === self::PARAMETER_STYLE_ASSOC) {
            $activeFacets = array_keys($activeFacets);
        }
        array_map(function($activeFacet) use (&$values, $facetName) {
            $parts = explode(':', $activeFacet, 2);
            if ($parts[0] === $facetName) {
                $values[] = $parts[1];
            }
        }, $activeFacets);

        return $values;
    }

    /**
     * Returns the active facets
     *
     * @return array
     */
    public function getActiveFacets(): array
    {
        $path = $this->prefixWithNamespace('filter');
        $pathValue = $this->argumentsAccessor->get($path, []);

        if (!is_array($pathValue)) {
            $pathValue = [];
        }

        // Sort url parameter
        if ($this->sort && !empty($pathValue)) {
            ParameterSortingUtility::sortByType(
                $pathValue,
                $this->parameterStyle
            );
        }

        return $pathValue;
    }

    /**
     * Returns the active count of facets
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->getActiveFacets());
    }

    /**
     * Sets and overwrite the active facets
     *
     * @param array $activeFacets
     *
     * @return UrlFacetContainer
     */
    public function setActiveFacets(array $activeFacets = []): UrlFacetContainer
    {
        $path = $this->prefixWithNamespace('filter');
        $this->argumentsAccessor->set($path, $activeFacets);

        return $this;
    }

    /**
     * Adds a facet value to the request.
     *
     * @param string $facetName
     * @param mixed $facetValue
     *
     * @return UrlFacetContainer
     */
    public function addFacetValue(string $facetName, $facetValue): UrlFacetContainer
    {
        if ($this->hasFacetValue($facetName, $facetValue)) {
            return $this;
        }

        $facetValues = $this->getActiveFacets();
        if ($this->parameterStyle === self::PARAMETER_STYLE_INDEX) {
            $facetValues[] = $facetName . ':' . $facetValue;
        } else {
            $facetValues[$facetName . ':' . $facetValue] = 1;
        }

        $this->changed = true;
        $this->setActiveFacets($facetValues);

        return $this;
    }

    /**
     * Removes a facet value from the request.
     *
     * @param string $facetName
     * @param mixed $facetValue
     *
     * @return UrlFacetContainer
     */
    public function removeFacetValue(string $facetName, $facetValue): UrlFacetContainer
    {
        if (!$this->hasFacetValue($facetName, $facetValue)) {
            return $this;
        }
        $facetValues = $this->getActiveFacets();
        $facetValueToLookFor = $facetName . ':' . $facetValue;

        if ($this->parameterStyle === self::PARAMETER_STYLE_INDEX) {
            foreach ($facetValues as $index => $facetValue) {
                if ($facetValue === $facetValueToLookFor) {
                    unset($facetValues[$index]);
                    break;
                }
            }
        } else {
            if (isset($facetValues[$facetValueToLookFor])) {
                unset($facetValues[$facetValueToLookFor]);
            }
        }
        $this->changed = true;
        $this->setActiveFacets($facetValues);

        return $this;
    }

    /**
     * Removes all facet values from the request by a certain facet name
     *
     * @param string $facetName
     *
     * @return UrlFacetContainer
     */
    public function removeAllFacetValuesByName(string $facetName): UrlFacetContainer
    {
        $facetValues = $this->getActiveFacets();
        $filterOptions = 0;
        if ($this->parameterStyle === self::PARAMETER_STYLE_ASSOC) {
            $filterOptions = ARRAY_FILTER_USE_KEY;
        }

        $facetValues = array_filter($facetValues, function($facetNameValue) use ($facetName) {
            $parts = explode(':', $facetNameValue, 2);
            return $parts[0] !== $facetName;
        }, $filterOptions);

        $this->changed = true;
        $this->setActiveFacets($facetValues);

        return $this;
    }

    /**
     * Removes all active facets from the request.
     *
     * @return UrlFacetContainer
     */
    public function removeAllFacets(): UrlFacetContainer
    {
        $path = $this->prefixWithNamespace('filter');
        $this->argumentsAccessor->reset($path);
        $this->changed = true;
        return $this;
    }

    /**
     * Test if there is a active facet with a given value
     *
     * @param string $facetName
     * @param mixed $facetValue
     * @return bool
     */
    public function hasFacetValue(string $facetName, $facetValue): bool
    {
        $facetNameAndValueToCheck = $facetName . ':' . $facetValue;
        $facetValues = $this->getActiveFacets();

        if ($this->parameterStyle === self::PARAMETER_STYLE_INDEX) {
            return in_array($facetNameAndValueToCheck, $this->getActiveFacets());
        } else {
            return isset($facetValues[$facetNameAndValueToCheck]) && (int)$facetValues[$facetNameAndValueToCheck] === 1;
        }
    }

    /**
     * Returns the information if the data bag has changes
     *
     * @return bool
     */
    public function hasChanged(): bool
    {
        return $this->changed;
    }

    /**
     * Resets the internal change status by explicit acknowledge the change
     *
     * @return $this
     */
    public function acknowledgeChange(): UrlFacetContainer
    {
        $this->changed = false;

        return $this;
    }
}
