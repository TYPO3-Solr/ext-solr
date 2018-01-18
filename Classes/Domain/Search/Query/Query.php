<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper\Pagination;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper\QueryStringContainer;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\BigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Debug;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Elevation;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Faceting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\FieldCollapsing;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Filters;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Grouping;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Highlighting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Operator;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\PhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\QueryFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\ReturnFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Slops;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Sorting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Spellchecking;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\TrigramPhraseFields;

/**
 * A Solr search query
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class Query
{
    /**
     * @var QueryStringContainer
     */
    protected $queryStringContainer = null;

    /**
     * @var Pagination
     */
    protected $pagination = null;

    /**
     * @var Operator
     */
    protected $operator = null;

    /**
     * ParameterBuilder for filters.
     *
     * @var Filters
     */
    protected $filters = null;

    /**
     * Holds the query fields with their associated boosts. The key represents
     * the field name, value represents the field's boost. These are the fields
     * that will actually be searched.
     *
     * Used in Solr's qf parameter
     *
     * @var QueryFields
     * @see http://wiki.apache.org/solr/DisMaxQParserPlugin#qf_.28Query_Fields.29
     */
    protected $queryFields = null;

    /**
     * Holds the phrase fields with their associated boosts. The key represents
     * the field name, value represents the field's boost. These are the fields
     * for those Apache Solr should build phrase quieries and by phrase occurrences should be boosted.
     *
     * @var PhraseFields
     * @see https://lucene.apache.org/solr/guide/7_0/the-dismax-query-parser.html#pf-phrase-fields-parameter
     */
    protected $phraseFields;

    /**
     * Holds the bigram phrase fields with their associated boosts. The key represents
     * the field name, value represents the field's boost. These are the fields
     * for those Apache Solr should build the phrases from triplets and sentences.
     *
     * @var BigramPhraseFields
     * @see "pf2" https://lucene.apache.org/solr/guide/7_0/the-extended-dismax-query-parser.html#extended-dismax-parameters
     */
    protected $bigramPhraseFields;

    /**
     * Holds the trigram phrase fields with their associated boosts. The key represents
     * the field name, value represents the field's boost. These are the fields
     * for those Apache Solr should build the phrases from triplets and sentences.
     *
     * @var TrigramPhraseFields
     * @see "pf3" https://lucene.apache.org/solr/guide/7_0/the-extended-dismax-query-parser.html#extended-dismax-parameters
     */
    protected $trigramPhraseFields;

    /**
     * List of fields that will be returned in the result documents.
     *
     * used in Solr's fl parameter
     *
     * @var ReturnFields
     * @see http://wiki.apache.org/solr/CommonQueryParameters#fl
     */
    protected $returnFields = null;

    /**
     * ParameterBuilder for the highlighting.
     *
     * @var Highlighting
     */
    protected $highlighting = null;

    /**
     * ParameterBuilder for the faceting.
     *
     * @var Faceting
     */
    protected $faceting = null;

    /**
     * ParameterBuilder for the spellchecking
     *
     * @var Spellchecking
     */
    protected $spellchecking = null;

    /**
     * ParameterBuilder for the grouping.
     *
     * @var Grouping
     */
    protected $grouping = null;

    /**
     * ParameterBuilder for the field collapsing (variants)
     *
     * @var FieldCollapsing
     */
    protected $fieldCollapsing = null;

    /**
     * ParameterBuilder for the debugging.
     *
     * @var Debug
     */
    protected $debug = null;

    /**
     * ParameterBuilder for the sorting
     *
     * @var Sorting
     */
    protected $sorting = null;

    /**
     * ParameterBuilder for the slops (qs,ps,ps2,ps3)
     *
     * @var Slops
     */
    protected $slops = null;

    /**
     * ParameterBuilder for the elevation
     *
     * @var Elevation
     */
    protected $elevation = null;

    /**
     * @var QueryParametersContainer
     */
    protected $queryParametersContainer = null;

    /**
     * Query constructor.
     * @param string $keywords
     */
    public function __construct($keywords)
    {
        $this->queryStringContainer = new QueryStringContainer((string)$keywords);
        $this->pagination = new Pagination();
        $this->filters = new Filters();
        $this->queryFields = QueryFields::fromString('*');
        $this->returnFields = ReturnFields::fromArray(['*', 'score']);

        $this->faceting = new Faceting(false);
        $this->grouping = new Grouping(false);
        $this->highlighting = new Highlighting(false);
        $this->bigramPhraseFields = new BigramPhraseFields(false);
        $this->trigramPhraseFields = new TrigramPhraseFields(false);
        $this->phraseFields = new PhraseFields(false);
        $this->spellchecking = new Spellchecking(false);
        $this->debug = new Debug(false);
        $this->sorting = new Sorting(false);
        $this->fieldCollapsing = new FieldCollapsing(false);
        $this->elevation = new Elevation(false);
        $this->operator = new Operator(false);
        $this->slops = new Slops();

        $this->queryParametersContainer = new QueryParametersContainer();
    }

    /**
     * @param QueryFields $queryFields
     */
    public function setQueryFields(QueryFields $queryFields)
    {
        $this->queryFields = $queryFields;
    }

    /**
     * @return QueryFields
     */
    public function getQueryFields()
    {
        return $this->queryFields;
    }

    /**
     * @param PhraseFields $phraseFields
     * @return void
     */
    public function setPhraseFields(PhraseFields $phraseFields)
    {
        $this->phraseFields = $phraseFields;
    }

    /**
     * @return PhraseFields
     */
    public function getPhraseFields()
    {
        return $this->phraseFields;
    }

    /**
     * @return BigramPhraseFields
     */
    public function getBigramPhraseFields()
    {
        return $this->bigramPhraseFields;
    }

    /**
     * @param BigramPhraseFields $bigramPhraseFields
     * @return void
     */
    public function setBigramPhraseFields(BigramPhraseFields $bigramPhraseFields)
    {
        $this->bigramPhraseFields = $bigramPhraseFields;
    }

    /**
     * @return TrigramPhraseFields
     */
    public function getTrigramPhraseFields()
    {
        return $this->trigramPhraseFields;
    }

    /**
     * @param TrigramPhraseFields $trigramPhraseFields
     * @return void
     */
    public function setTrigramPhraseFields(TrigramPhraseFields $trigramPhraseFields)
    {
        $this->trigramPhraseFields = $trigramPhraseFields;
    }

    /**
     * returns a string representation of the query
     *
     * @return string the string representation of the query
     */
    public function __toString()
    {
        return $this->queryStringContainer->__toString();
    }

    /**
     * Builds the query string which is then used for Solr's q parameters
     *
     * @return QueryStringContainer
     */
    public function getQueryStringContainer()
    {
        return $this->queryStringContainer;
    }

    /**
     * @param QueryStringContainer $queryStringContainer
     */
    public function setQueryStringContainer(QueryStringContainer $queryStringContainer)
    {
        $this->queryStringContainer = $queryStringContainer;
    }

    /**
     * @return Pagination
     */
    public function getPagination(): Pagination
    {
        return $this->pagination;
    }

    /**
     * @param Pagination $pagination
     */
    public function setPagination(Pagination $pagination)
    {
        $this->pagination = $pagination;
    }

    // query elevation

    /**
     * @param Elevation $elevation
     */
    public function setElevation(Elevation $elevation)
    {
        $this->elevation = $elevation;
    }

    /**
     * @return Elevation
     */
    public function getElevation(): Elevation
    {
        return $this->elevation;
    }

    // collapsing

    /**
     * @param FieldCollapsing $fieldCollapsing
     */
    public function setFieldCollapsing(FieldCollapsing $fieldCollapsing)
    {
        $this->fieldCollapsing = $fieldCollapsing;
    }

    /**
     * @return FieldCollapsing
     */
    public function getFieldCollapsing(): FieldCollapsing
    {
        return $this->fieldCollapsing;
    }

    // grouping

    /**
     * Activates and deactivates grouping for the current query.
     *
     * @param Grouping $grouping TRUE to enable grouping, FALSE to disable grouping
     * @return void
     */
    public function setGrouping(Grouping $grouping)
    {
        $this->grouping = $grouping;
    }

    /**
     * @return Grouping
     */
    public function getGrouping(): Grouping
    {
        return $this->grouping;
    }

    /**
     * Returns the number of results that should be shown per page or the number of groups, when grouping is active
     *
     * @return int number of results to show per page
     */
    public function getRows()
    {
        if ($this->getGrouping() instanceof Grouping && $this->getGrouping()->getIsEnabled()) {
            return $this->getGrouping()->getNumberOfGroups();
        }

        return $this->getPagination()->getResultsPerPage();
    }

    // faceting

    /**
     * Activates and deactivates faceting for the current query.
     *
     * @param Faceting $faceting TRUE to enable faceting, FALSE to disable faceting
     * @return void
     */
    public function setFaceting(Faceting $faceting)
    {
        $this->faceting = $faceting;
    }

    /**
     * @return Faceting
     */
    public function getFaceting(): Faceting
    {
        return $this->faceting;
    }

    /**
     * Sets the filters to use.
     *
     * @param Filters $filters
     */
    public function setFilters(Filters $filters)
    {
        $this->filters = $filters;
    }

    /**
     * Gets all currently applied filters.
     *
     * @return Filters Array of filters
     */
    public function getFilters(): Filters
    {
        return $this->filters;
    }

    /**
     * @param ReturnFields $returnFields
     */
    public function setReturnFields(ReturnFields $returnFields)
    {
        $this->returnFields = $returnFields;
    }

    /**
     * @return ReturnFields
     */
    public function getReturnFields(): ReturnFields
    {
        return $this->returnFields;
    }

    /**
     * Gets the query type, Solr's qt parameter.
     *
     * @return string Query type, qt parameter.
     */
    public function getQueryType()
    {
        return $this->queryParametersContainer->get('qt');
    }

    /**
     * Sets the query type, Solr's qt parameter.
     *
     * @param string|bool $queryType String query type or boolean FALSE to disable / reset the qt parameter.
     * @see http://wiki.apache.org/solr/CoreQueryParameters#qt
     */
    public function setQueryType($queryType)
    {
        $this->queryParametersContainer->setWhenStringOrUnsetWhenEmpty('qt', $queryType);
    }

    /**
     * Set the operator that should be used for the query. Operators an be created e.g. by using
     * Operator::and()
     *
     * @param Operator $operator
     */
    public function setOperator(Operator $operator)
    {
        $this->operator = $operator;
    }

    /**
     * Returns the operator of the query.
     *
     * @return Operator
     */
    public function getOperator(): Operator
    {
        return $this->operator;
    }

    /**
     * @return Slops
     */
    public function getSlops(): Slops
    {
        return $this->slops;
    }

    /**
     * @param Slops $slops
     */
    public function setSlops(Slops $slops)
    {
        $this->slops = $slops;
    }

    /**
     * Gets the alternative query, Solr's q.alt parameter.
     *
     * @return string Alternative query, q.alt parameter.
     */
    public function getAlternativeQuery()
    {
        return $this->queryParametersContainer->get('q.alt');
    }

    /**
     * Sets an alternative query, Solr's q.alt parameter.
     *
     * This query supports the complete Lucene Query Language.
     *
     * @param string $alternativeQuery String alternative query or boolean FALSE to disable / reset the q.alt parameter.
     * @see http://wiki.apache.org/solr/DisMaxQParserPlugin#q.alt
     */
    public function setAlternativeQuery($alternativeQuery)
    {
        $this->queryParametersContainer->setWhenStringOrUnsetWhenEmpty('q.alt', $alternativeQuery);
    }

    // keywords

    /**
     * Set the query to omit the response header
     *
     * @param bool $omitHeader TRUE (default) to omit response headers, FALSE to re-enable
     */
    public function setOmitHeader($omitHeader = true)
    {
        $omitHeader = ($omitHeader === true) ? 'true' : $omitHeader;
        $this->queryParametersContainer->setWhenStringOrUnsetWhenEmpty('omitHeader', $omitHeader);
    }

    /**
     * Sets the minimum match (mm) parameter
     *
     * @param mixed $minimumMatch Minimum match parameter as string or boolean FALSE to disable / reset the mm parameter
     * @see http://wiki.apache.org/solr/DisMaxRequestHandler#mm_.28Minimum_.27Should.27_Match.29
     */
    public function setMinimumMatch($minimumMatch)
    {
        $this->queryParametersContainer->setWhenStringOrUnsetWhenEmpty('mm', $minimumMatch);
    }

    /**
     * Sets the boost function (bf) parameter
     *
     * @param mixed $boostFunction boost function parameter as string or boolean FALSE to disable / reset the bf parameter
     * @see http://wiki.apache.org/solr/DisMaxRequestHandler#bf_.28Boost_Functions.29
     */
    public function setBoostFunction($boostFunction)
    {
        $this->queryParametersContainer->setWhenStringOrUnsetWhenEmpty('bf', $boostFunction);
    }

    /**
     * Sets the boost query (bq) parameter
     *
     * @param mixed $boostQuery boost query parameter as string or array to set a boost query or boolean FALSE to disable / reset the bq parameter
     * @see http://wiki.apache.org/solr/DisMaxQParserPlugin#bq_.28Boost_Query.29
     */
    public function setBoostQuery($boostQuery)
    {
        if (is_array($boostQuery)) {
            $this->queryParametersContainer->set('bq', $boostQuery);
            return;
        }
        $this->queryParametersContainer->setWhenStringOrUnsetWhenEmpty('bq', $boostQuery);
    }

    /**
     * Set the tie breaker (tie) parameter
     *
     * @param mixed $tieParameter tie breaker parameter as string or boolean FALSE to disable / reset the tie parameter
     * @return void
     */
    public function setTieParameter($tieParameter)
    {
        $this->queryParametersContainer->setWhenStringOrUnsetWhenEmpty('tie', $tieParameter);
    }

    /**
     * Gets a specific query parameter by its name.
     *
     * @param string $parameterName The parameter to return
     * @param mixed $defaultIfEmpty
     * @return mixed The parameter's value or $defaultIfEmpty if not set
     */
    public function getQueryParameter($parameterName, $defaultIfEmpty = null)
    {
        $parameters = $this->getQueryParameters();
        return isset($parameters[$parameterName]) ? $parameters[$parameterName] : $defaultIfEmpty;
    }

    /**
     * The build method calls build on all ParameterBuilder that fill the QueryParameterContainer
     *
     * @return void
     */
    protected function build()
    {
        $this->getQueryFields()->build($this);
        $this->getPhraseFields()->build($this);
        $this->getBigramPhraseFields()->build($this);
        $this->getTrigramPhraseFields()->build($this);
        $this->getHighlighting()->build($this);
        $this->getFaceting()->build($this);
        $this->getGrouping()->build($this);
        $this->getSpellchecking()->build($this);
        $this->getFieldCollapsing()->build($this);
        $this->getElevation()->build($this);

        $this->debug->build($this);
        $this->sorting->build($this);
        $this->operator->build($this);
        $this->slops->build($this);

        // it is important that this parameters get build in the end because other builders add filters and return fields
        $this->getReturnFields()->build($this);
        $this->getFilters()->build($this);
    }

    /**
     * Builds an array of query parameters to use for the search query.
     *
     * @return array An array ready to use with query parameters
     */
    public function getQueryParameters()
    {
        $this->build();
        return $this->queryParametersContainer->toArray();
    }

    /**
     * @return QueryParametersContainer
     */
    public function getQueryParametersContainer(): QueryParametersContainer
    {
        return $this->queryParametersContainer;
    }

    /**
     * Adds a parameter to the query.
     *
     * @param string $parameterName
     * @param mixed $value
     */
    public function addQueryParameter($parameterName, $value)
    {
        $this->queryParametersContainer->set($parameterName, $value);
    }

    // general query parameters

    /**
     * Enables or disables highlighting of search terms in result teasers.
     *
     * @param Highlighting $highlighting
     * @see http://wiki.apache.org/solr/HighlightingParameters
     * @return void
     */
    public function setHighlighting(Highlighting $highlighting)
    {
        $this->highlighting = $highlighting;
    }

    /**
     * @return Highlighting
     */
    public function getHighlighting(): Highlighting
    {
        return $this->highlighting;
    }

    // misc
    /**
     * @param Spellchecking $spellchecking
     */
    public function setSpellchecking(Spellchecking $spellchecking)
    {
        $this->spellchecking = $spellchecking;
    }

    /**
     * @return Spellchecking
     */
    public function getSpellchecking(): Spellchecking
    {
        return $this->spellchecking;
    }

    /**
     * Sets the sort parameter.
     *
     * $sorting must include a field name (or the pseudo-field score),
     * followed by a space,
     * followed by a sort direction (asc or desc).
     *
     * Multiple fallback sortings can be separated by comma,
     * ie: <field name> <direction>[,<field name> <direction>]...
     *
     * @param string|bool $sorting Either a comma-separated list of sort fields and directions or FALSE to reset sorting to the default behavior (sort by score / relevance)
     * @see http://wiki.apache.org/solr/CommonQueryParameters#sort
     */
    public function setSorting($sorting)
    {
        $sorting = trim((string)$sorting);
        $enabled = $sorting !== '';
        $this->sorting->setIsEnabled($enabled);
        $this->sorting->setSortField($sorting);
    }

    /**
     * Enables or disables the debug parameter for the query.
     *
     * @param bool $debugMode Enables debugging when set to TRUE, deactivates debugging when set to FALSE, defaults to TRUE.
     */
    public function setDebugMode($debugMode = true)
    {
        $this->debug->setIsEnabled($debugMode);
    }
}
