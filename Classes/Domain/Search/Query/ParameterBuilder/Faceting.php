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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\AbstractQueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\SortingExpression;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * The Faceting ParameterProvider is responsible to build the solr query parameters
 * that are needed for the highlighting.
 */
class Faceting extends AbstractDeactivatable implements ParameterBuilderInterface
{
    protected string $sorting = '';

    protected int $minCount = 1;

    protected int $limit = 10;

    /**
     * @var string[]
     */
    protected array $fields = [];

    protected array $additionalParameters = [];

    public function __construct(
        bool $isEnabled,
        string $sorting = '',
        int $minCount = 1,
        int $limit = 10,
        array $fields = [],
        array $additionalParameters = [],
    ) {
        $this->isEnabled = $isEnabled;
        $this->sorting = $sorting;
        $this->minCount = $minCount;
        $this->limit = $limit;
        $this->fields = $fields;
        $this->additionalParameters = $additionalParameters;
    }

    public function getSorting(): string
    {
        return $this->sorting;
    }

    public function setSorting(string $sorting): void
    {
        $this->sorting = $sorting;
    }

    public function getMinCount(): int
    {
        return $this->minCount;
    }

    public function setMinCount(int $minCount): void
    {
        $this->minCount = $minCount;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @return string[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param string[] $fields
     */
    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    public function addField(string $fieldName): void
    {
        $this->fields[] = $fieldName;
    }

    public function getAdditionalParameters(): array
    {
        return $this->additionalParameters;
    }

    public function setAdditionalParameters(array $additionalParameters): void
    {
        $this->additionalParameters = $additionalParameters;
    }

    public function addAdditionalParameter(string $key, mixed $value): void
    {
        $this->additionalParameters[$key] = $value;
    }

    /**
     * Reads the facet sorting configuration and applies it to the queryParameters.
     */
    protected function applySorting(array $facetParameters): array
    {
        $sortingExpression = new SortingExpression();
        $globalSortingExpression = $sortingExpression->getForFacet($this->sorting);

        if (!empty($globalSortingExpression)) {
            $facetParameters['facet.sort'] = $globalSortingExpression;
        }

        return $facetParameters;
    }

    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration): Faceting
    {
        $isEnabled = $solrConfiguration->getSearchFaceting();
        if (!$isEnabled) {
            return new Faceting(false);
        }

        $minCount = $solrConfiguration->getSearchFacetingMinimumCount();
        $limit = $solrConfiguration->getSearchFacetingFacetLimit();
        $sorting = $solrConfiguration->getSearchFacetingSortBy();

        return new Faceting(true, $sorting, $minCount, $limit);
    }

    public static function getEmpty(): Faceting
    {
        return new Faceting(false);
    }

    /**
     * Retrieves all parameters that are required for faceting.
     */
    protected function getFacetParameters(): array
    {
        $facetParameters = [];
        $facetParameters['facet'] = 'true';
        $facetParameters['facet.mincount'] = $this->getMinCount();
        $facetParameters['facet.limit'] = $this->getLimit();
        $facetParameters['facet.field'] = $this->getFields();

        foreach ($this->getAdditionalParameters() as $additionalParameterKey => $additionalParameterValue) {
            $facetParameters[$additionalParameterKey] = $additionalParameterValue;
        }

        if (isset($facetParameters['json.facet']) && $facetParameters['json.facet']) {
            $facetParameters['json.facet'] = json_encode($facetParameters['json.facet']);
        }

        return $this->applySorting($facetParameters);
    }

    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $query = $parentBuilder->getQuery();
        if (!$this->getIsEnabled()) {
            //@todo use unset functionality when present
            $query->addParam('facet', null);
            $query->addParam('lex', null);
            $query->addParam('json.mincount', null);
            $query->addParam('json.limit', null);
            $query->addParam('json.field', null);
            $query->addParam('facet.sort', null);

            $params = $query->getParams();
            foreach ($params as $key => $value) {
                if (str_contains($key, 'f.')) {
                    $query->addParam($key, null);
                }
            }

            return $parentBuilder;
        }

        //@todo check of $this->queryToBuilder->getFacetSet() can be used
        $facetingParameters = $this->getFacetParameters();
        foreach ($facetingParameters as $key => $value) {
            $query->addParam($key, $value);
        }

        return $parentBuilder;
    }
}
