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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\BigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Elevation;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Faceting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\FieldCollapsing;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Grouping;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Highlighting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Operator;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\PhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\QueryFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\ReturnFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Slops;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Sorting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Sortings;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Spellchecking;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\TrigramPhraseFields;
use Closure;

/**
 * The AbstractQueryBuilder contains all logic to initialize solr queries independent of TYPO3.
 */
abstract class AbstractQueryBuilder
{
    protected Query|SearchQuery|SuggestQuery|null $queryToBuild;

    /**
     * @return static
     */
    public function startFrom(Query $query): self
    {
        $this->queryToBuild = $query;
        return $this;
    }

    public function getQuery(): Query
    {
        return $this->queryToBuild;
    }

    public function useOmitHeader(bool $omitHeader = true): self
    {
        $this->queryToBuild->setOmitHeader($omitHeader);

        return $this;
    }

    /**
     * Uses an array of filters and applies them to the query.
     */
    public function useFilterArray(array $filterArray): self
    {
        foreach ($filterArray as $key => $additionalFilter) {
            $this->useFilter($additionalFilter, (string)$key);
        }

        return $this;
    }

    /**
     * Applies the queryString that is used to search
     */
    public function useQueryString(string $queryString): self
    {
        $this->queryToBuild->setQuery($queryString);
        return $this;
    }

    /**
     * Applies the passed queryType to the query.
     */
    public function useQueryType(string $queryType): self
    {
        $this->queryToBuild->addParam('qt', $queryType);
        return $this;
    }

    /**
     * Remove the queryType (qt) from the query.
     */
    public function removeQueryType(): self
    {
        $this->queryToBuild->addParam('qt', null);
        return $this;
    }

    /**
     * Can be used to remove all sortings from the query.
     */
    public function removeAllSortings(): self
    {
        $this->queryToBuild->clearSorts();
        return $this;
    }

    /**
     * Applies the passed sorting to the query.
     */
    public function useSorting(Sorting $sorting): self
    {
        if (str_contains($sorting->getFieldName(), 'relevance')) {
            $this->removeAllSortings();
            return $this;
        }

        $this->queryToBuild->addSort($sorting->getFieldName(), $sorting->getDirection());
        return $this;
    }

    /**
     * Applies the passed sorting to the query.
     */
    public function useSortings(Sortings $sortings): self
    {
        foreach ($sortings->getSortings() as $sorting) {
            $this->useSorting($sorting);
        }

        return $this;
    }

    public function useResultsPerPage(int $resultsPerPage): self
    {
        $this->queryToBuild->setRows($resultsPerPage);
        return $this;
    }

    public function usePage(int $page): self
    {
        $this->queryToBuild->setStart($page);
        return $this;
    }

    public function useOperator(Operator $operator): self
    {
        $this->queryToBuild->setQueryDefaultOperator($operator->getOperator());
        return $this;
    }

    /**
     * Remove the default query operator.
     */
    public function removeOperator(): self
    {
        $this->queryToBuild->setQueryDefaultOperator('');
        return $this;
    }

    public function useSlops(Slops $slops): self
    {
        return $slops->build($this);
    }

    /**
     * Uses the passed boostQuer(y|ies) for the query.
     */
    public function useBoostQueries(array|string $boostQueries): self
    {
        $boostQueryArray = [];
        if (is_array($boostQueries)) {
            foreach ($boostQueries as $boostQuery) {
                $boostQueryArray[] = ['key' => hash('md5', $boostQuery), 'query' => $boostQuery];
            }
        } else {
            $boostQueryArray[] = ['key' => hash('md5', $boostQueries), 'query' => $boostQueries];
        }

        $this->queryToBuild->getEDisMax()->setBoostQueries($boostQueryArray);
        return $this;
    }

    /**
     * Removes all boost queries from the query.
     */
    public function removeAllBoostQueries(): self
    {
        $this->queryToBuild->getEDisMax()->clearBoostQueries();
        return $this;
    }

    /**
     * Uses the passed boostFunction for the query.
     */
    public function useBoostFunction(string $boostFunction): self
    {
        $this->queryToBuild->getEDisMax()->setBoostFunctions($boostFunction);
        return $this;
    }

    /**
     * Removes all previously configured boost functions.
     */
    public function removeAllBoostFunctions(): self
    {
        $this->queryToBuild->getEDisMax()->setBoostFunctions('');
        return $this;
    }

    /**
     * Uses the passed minimumMatch(mm) for the query.
     */
    public function useMinimumMatch(string $minimumMatch): self
    {
        $this->queryToBuild->getEDisMax()->setMinimumMatch($minimumMatch);
        return $this;
    }

    /**
     * Remove any previous passed minimumMatch parameter.
     */
    public function removeMinimumMatch(): self
    {
        $this->queryToBuild->getEDisMax()->setMinimumMatch('');
        return $this;
    }

    /**
     * Applies the tie parameter to the query.
     */
    public function useTieParameter(float $tie): self
    {
        $this->queryToBuild->getEDisMax()->setTie($tie);
        return $this;
    }

    /**
     * Applies custom QueryFields to the query.
     */
    public function useQueryFields(QueryFields $queryFields): self
    {
        return $queryFields->build($this);
    }

    /**
     * Applies custom ReturnFields to the query.
     */
    public function useReturnFields(ReturnFields $returnFields): self
    {
        return $returnFields->build($this);
    }

    /**
     * Can be used to use a specific filter string in the solr query.
     * @return static
     */
    public function useFilter(string $filterString, string $filterName = ''): self
    {
        $filterName = $filterName === '' ? $filterString : $filterName;

        $nameWasPassedAndFilterIsAlreadySet = $filterName !== '' && $this->queryToBuild->getFilterQuery($filterName) !== null;
        if ($nameWasPassedAndFilterIsAlreadySet) {
            return $this;
        }
        $this->queryToBuild->addFilterQuery(['key' => $filterName, 'query' => $filterString]);
        return $this;
    }

    /**
     * Removes a filter by the fieldName.
     */
    public function removeFilterByFieldName(string $fieldName): self
    {
        return $this->removeFilterByFunction(
            function ($key, $query) use ($fieldName) {
                $queryString = $query->getQuery();
                $storedFieldName = substr($queryString, 0, strpos($queryString, ':'));
                return $storedFieldName == $fieldName;
            },
        );
    }

    /**
     * Removes a filter by the name of the filter (also known as key).
     */
    public function removeFilterByName(string $name): self
    {
        return $this->removeFilterByFunction(
            function ($key, $query) use ($name) {
                return $query->getKey() === $name;
            },
        );
    }

    /**
     * Removes a filter by the filter value.
     */
    public function removeFilterByValue(string $value): self
    {
        return $this->removeFilterByFunction(
            function ($key, $query) use ($value) {
                $query = $query->getQuery();
                return $query == $value;
            },
        );
    }

    public function removeFilterByFunction(Closure $filterFunction): self
    {
        $queries = $this->queryToBuild->getFilterQueries();
        foreach ($queries as $key => $query) {
            $canBeRemoved = $filterFunction($key, $query);
            if ($canBeRemoved) {
                unset($queries[$key]);
            }
        }

        $this->queryToBuild->setFilterQueries($queries);
        return $this;
    }

    /**
     * Passes the alternative query to the SolariumQuery
     */
    public function useAlternativeQuery(string $query): self
    {
        $this->queryToBuild->getEDisMax()->setQueryAlternative($query);
        return $this;
    }

    /**
     * Remove the alternative query from the SolariumQuery.
     */
    public function removeAlternativeQuery(): self
    {
        $this->queryToBuild->getEDisMax()->setQueryAlternative(null);
        return $this;
    }

    /**
     * Applies a custom Faceting configuration to the query.
     */
    public function useFaceting(Faceting $faceting): self
    {
        return $faceting->build($this);
    }

    public function useFieldCollapsing(FieldCollapsing $fieldCollapsing): self
    {
        return $fieldCollapsing->build($this);
    }

    /**
     * Applies a custom initialized grouping to the query.
     */
    public function useGrouping(Grouping $grouping): self
    {
        return $grouping->build($this);
    }

    public function useHighlighting(Highlighting $highlighting): self
    {
        return $highlighting->build($this);
    }

    public function useDebug(bool $debugMode): self
    {
        if (!$debugMode) {
            $this->queryToBuild->addParam('debugQuery', null);
            $this->queryToBuild->addParam('echoParams', null);
            return $this;
        }

        $this->queryToBuild->addParam('debugQuery', 'true');
        $this->queryToBuild->addParam('echoParams', 'all');

        return $this;
    }

    public function useElevation(Elevation $elevation): self
    {
        return $elevation->build($this);
    }

    public function useSpellchecking(Spellchecking $spellchecking): self
    {
        return $spellchecking->build($this);
    }

    /**
     * Applies a custom configured PhraseFields to the query.
     */
    public function usePhraseFields(PhraseFields $phraseFields): self
    {
        return $phraseFields->build($this);
    }

    /**
     * Applies a custom configured BigramPhraseFields to the query.
     */
    public function useBigramPhraseFields(BigramPhraseFields $bigramPhraseFields): self
    {
        return $bigramPhraseFields->build($this);
    }

    /**
     * Applies a custom configured TrigramPhraseFields to the query.
     */
    public function useTrigramPhraseFields(TrigramPhraseFields $trigramPhraseFields): self
    {
        return $trigramPhraseFields->build($this);
    }

    abstract public function useBoostQueriesFromTypoScript(): self;

    abstract public function useInitialQueryFromTypoScript(): self;

    abstract public function useTieParameterFromTypoScript(): self;

    abstract public function useFacetingFromTypoScript(): self;

    abstract public function useVariantsFromTypoScript(): self;

    abstract public function useGroupingFromTypoScript(): self;

    abstract public function usePhraseFieldsFromTypoScript(): self;

    abstract public function useBigramPhraseFieldsFromTypoScript(): self;

    abstract public function useTrigramPhraseFieldsFromTypoScript(): self;

    abstract public function useReturnFieldsFromTypoScript(): self;

    abstract public function useQueryFieldsFromTypoScript(): self;

    abstract public function useFiltersFromTypoScript(): self;

    abstract public function useHighlightingFromTypoScript(): self;
}
