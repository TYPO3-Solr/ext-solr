<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * The AbstractQueryBuilder contains all logic to initialize solr queries independent from TYPO3.
 */
abstract class AbstractQueryBuilder {

    /**
     * @var Query
     */
    protected $queryToBuild = null;

    /**
     * @param Query $query
     * @return $this
     */
    public function startFrom(Query $query)
    {
        $this->queryToBuild = $query;
        return $this;
    }

    /**
     * @return Query
     */
    public function getQuery(): Query
    {
        return $this->queryToBuild;
    }

    /**
     * @param bool $omitHeader
     * @return $this
     */
    public function useOmitHeader($omitHeader = true)
    {
        $this->queryToBuild->setOmitHeader($omitHeader);

        return $this;
    }

    /**
     * Uses an array of filters and applies them to the query.
     *
     * @param array $filterArray
     * @return $this
     */
    public function useFilterArray(array $filterArray)
    {
        foreach ($filterArray as $key => $additionalFilter) {
            $this->useFilter($additionalFilter, $key);
        }

        return $this;
    }

    /**
     * Applies the queryString that is used to search
     *
     * @param string $queryString
     * @return $this
     */
    public function useQueryString($queryString)
    {
        $this->queryToBuild->setQuery($queryString);
        return $this;
    }

    /**
     * Applies the passed queryType to the query.
     *
     * @param string $queryType
     * @return $this
     */
    public function useQueryType(string $queryType)
    {
        $this->queryToBuild->addParam('qt', $queryType);
        return $this;
    }

    /**
     * Remove the queryType (qt) from the query.
     *
     * @return $this
     */
    public function removeQueryType()
    {
        $this->queryToBuild->addParam('qt', null);
        return $this;
    }

    /**
     * Can be used to remove all sortings from the query.
     *
     * @return $this
     */
    public function removeAllSortings()
    {
        $this->queryToBuild->clearSorts();
        return $this;
    }

    /**
     * Applies the passed sorting to the query.
     *
     * @param Sorting $sorting
     * @return $this
     */
    public function useSorting(Sorting $sorting)
    {
        if (strpos($sorting->getFieldName(), 'relevance') !== false) {
            $this->removeAllSortings();
            return $this;
        }

        $this->queryToBuild->addSort($sorting->getFieldName(), $sorting->getDirection());
        return $this;
    }

    /**
     * Applies the passed sorting to the query.
     *
     * @param Sortings $sortings
     * @return $this
     */
    public function useSortings(Sortings $sortings)
    {
        foreach($sortings->getSortings() as $sorting) {
            $this->useSorting($sorting);
        }

        return $this;
    }

    /**
     * @param int $resultsPerPage
     * @return $this
     */
    public function useResultsPerPage($resultsPerPage)
    {
        $this->queryToBuild->setRows($resultsPerPage);
        return $this;
    }

    /**
     * @param int $page
     * @return $this
     */
    public function usePage($page)
    {
        $this->queryToBuild->setStart($page);
        return $this;
    }

    /**
     * @param Operator $operator
     * @return $this
     */
    public function useOperator(Operator $operator)
    {
        $this->queryToBuild->setQueryDefaultOperator( $operator->getOperator());
        return $this;
    }

    /**
     * Remove the default query operator.
     *
     * @return $this
     */
    public function removeOperator()
    {
        $this->queryToBuild->setQueryDefaultOperator(null);
        return $this;
    }

    /**
     * @param Slops $slops
     * @return $this
     */
    public function useSlops(Slops $slops)
    {
        return $slops->build($this);
    }

    /**
     * Uses the passed boostQuer(y|ies) for the query.
     *
     * @param string|array $boostQueries
     * @return $this
     */
    public function useBoostQueries($boostQueries)
    {
        $boostQueryArray = [];
        if(is_array($boostQueries)) {
            foreach($boostQueries as $boostQuery) {
                $boostQueryArray[] = ['key' => md5($boostQuery), 'query' => $boostQuery];
            }
        } else {
            $boostQueryArray[] = ['key' => md5($boostQueries), 'query' => $boostQueries];
        }

        $this->queryToBuild->getEDisMax()->setBoostQueries($boostQueryArray);
        return $this;
    }

    /**
     * Removes all boost queries from the query.
     *
     * @return $this
     */
    public function removeAllBoostQueries()
    {
        $this->queryToBuild->getEDisMax()->clearBoostQueries();
        return $this;
    }

    /**
     * Uses the passed boostFunction for the query.
     *
     * @param string $boostFunction
     * @return $this
     */
    public function useBoostFunction(string $boostFunction)
    {
        $this->queryToBuild->getEDisMax()->setBoostFunctions($boostFunction);
        return $this;
    }

    /**
     * Removes all previously configured boost functions.
     *
     * @return $this
     */
    public function removeAllBoostFunctions()
    {
        $this->queryToBuild->getEDisMax()->setBoostFunctions(null);
        return $this;
    }


    /**
     * Uses the passed minimumMatch(mm) for the query.
     *
     * @param string $minimumMatch
     * @return $this
     */
    public function useMinimumMatch(string $minimumMatch)
    {
        $this->queryToBuild->getEDisMax()->setMinimumMatch($minimumMatch);
        return $this;
    }

    /**
     * Remove any previous passed minimumMatch parameter.
     *
     * @return $this
     */
    public function removeMinimumMatch()
    {
        $this->queryToBuild->getEDisMax()->setMinimumMatch(null);
        return $this;
    }


    /**
     * Applies the tie parameter to the query.
     *
     * @param mixed $tie
     * @return $this
     */
    public function useTieParameter($tie)
    {
        $this->queryToBuild->getEDisMax()->setTie($tie);
        return $this;
    }

    /**
     * Applies custom QueryFields to the query.
     *
     * @param QueryFields $queryFields
     * @return $this
     */
    public function useQueryFields(QueryFields $queryFields)
    {
        return $queryFields->build($this);
    }

    /**
     * Applies custom ReturnFields to the query.
     *
     * @param ReturnFields $returnFields
     * @return $this
     */
    public function useReturnFields(ReturnFields $returnFields)
    {
        return $returnFields->build($this);
    }

    /**
     * Can be used to use a specific filter string in the solr query.
     *
     * @param string $filterString
     * @param string $filterName
     * @return $this
     */
    public function useFilter($filterString, $filterName = '')
    {
        $filterName = $filterName === '' ? $filterString : $filterName;

        $nameWasPassedAndFilterIsAllreadySet = $filterName !== '' && $this->queryToBuild->getFilterQuery($filterName) !== null;
        if($nameWasPassedAndFilterIsAllreadySet) {
            return $this;
        }
        $this->queryToBuild->addFilterQuery(['key' => $filterName, 'query' => $filterString]);
        return $this;
    }

    /**
     * Removes a filter by the fieldName.
     *
     * @param string $fieldName
     * @return $this
     */
    public function removeFilterByFieldName($fieldName)
    {
        return $this->removeFilterByFunction(
            function($key, $query) use ($fieldName) {
                $queryString = $query->getQuery();
                $storedFieldName = substr($queryString,0, strpos($queryString, ":"));
                return $storedFieldName == $fieldName;
            }
        );
    }

    /**
     * Removes a filter by the name of the filter (also known as key).
     *
     * @param string $name
     * @return $this
     */
    public function removeFilterByName($name)
    {
        return $this->removeFilterByFunction(
            function($key, $query) use ($name) {
                $key = $query->getKey();
                return $key == $name;
            }
        );
    }

    /**
     * Removes a filter by the filter value.
     *
     * @param string $value
     * @return $this
     */
    public function removeFilterByValue($value)
    {
        return $this->removeFilterByFunction(
            function($key, $query) use ($value) {
                $query = $query->getQuery();
                return $query == $value;
            }
        );
    }

    /**
     * @param \Closure $filterFunction
     * @return $this
     */
    public function removeFilterByFunction($filterFunction)
    {
        $queries = $this->queryToBuild->getFilterQueries();
        foreach($queries as $key =>  $query) {
            $canBeRemoved = $filterFunction($key, $query);
            if($canBeRemoved) {
                unset($queries[$key]);
            }
        }

        $this->queryToBuild->setFilterQueries($queries);
        return $this;
    }

    /**
     * Passes the alternative query to the Query
     * @param string $query
     * @return $this
     */
    public function useAlternativeQuery(string $query)
    {
        $this->queryToBuild->getEDisMax()->setQueryAlternative($query);
        return $this;
    }

    /**
     * Remove the alternative query from the Query.
     *
     * @return $this
     */
    public function removeAlternativeQuery()
    {
        $this->queryToBuild->getEDisMax()->setQueryAlternative(null);
        return $this;
    }

    /**
     * Applies a custom Faceting configuration to the query.
     *
     * @param Faceting $faceting
     * @return $this
     */
    public function useFaceting(Faceting $faceting)
    {
        return $faceting->build($this);
    }

    /**
     * @param FieldCollapsing $fieldCollapsing
     * @return $this
     */
    public function useFieldCollapsing(FieldCollapsing $fieldCollapsing)
    {
        return $fieldCollapsing->build($this);
    }

    /**
     * Applies a custom initialized grouping to the query.
     *
     * @param Grouping $grouping
     * @return $this
     */
    public function useGrouping(Grouping $grouping)
    {
        return $grouping->build($this);
    }

    /**
     * @param Highlighting $highlighting
     * @return $this
     */
    public function useHighlighting(Highlighting $highlighting)
    {
        return $highlighting->build($this);
    }

    /**
     * @param boolean $debugMode
     * @return $this
     */
    public function useDebug($debugMode)
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

    /**
     * @param Elevation $elevation
     * @return QueryBuilder
     */
    public function useElevation(Elevation $elevation)
    {
        return $elevation->build($this);
    }

    /**
     * @param Spellchecking $spellchecking
     * @return $this
     */
    public function useSpellchecking(Spellchecking $spellchecking)
    {
        return $spellchecking->build($this);
    }

    /**
     * Applies a custom configured PhraseFields to the query.
     *
     * @param PhraseFields $phraseFields
     * @return $this
     */
    public function usePhraseFields(PhraseFields $phraseFields)
    {
        return $phraseFields->build($this);
    }

    /**
     * Applies a custom configured BigramPhraseFields to the query.
     *
     * @param BigramPhraseFields $bigramPhraseFields
     * @return $this
     */
    public function useBigramPhraseFields(BigramPhraseFields $bigramPhraseFields)
    {
        return $bigramPhraseFields->build($this);
    }

    /**
     * Applies a custom configured TrigramPhraseFields to the query.
     *
     * @param TrigramPhraseFields $trigramPhraseFields
     * @return $this
     */
    public function useTrigramPhraseFields(TrigramPhraseFields $trigramPhraseFields)
    {
        return $trigramPhraseFields->build($this);
    }
}
