<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

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
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;


/**
 * The Sorting ParameterProvider is responsible to build the solr query parameters
 * that are needed for the sorting.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder
 */
class Sorting extends AbstractDeactivatableParameterBuilder implements ParameterBuilder
{
    const SORT_ASC = 'ASC';
    const SORT_DESC = 'DESC';

    /**
     * @var string
     */
    protected $sortField = '';

    /**
     * Debug constructor.
     *
     * @param bool $isEnabled
     * @param string $sortField
     */
    public function __construct($isEnabled = false, $sortField = '')
    {
        $this->isEnabled = $isEnabled;
        $this->setSortField($sortField);
    }

    /**
     * @param Query $query
     * @return Query
     */
    public function build(Query $query): Query
    {
        $isEnabledAndField = $this->isEnabled && !empty($this->sortField);
        if (!$isEnabledAndField) {
            $query->getQueryParametersContainer()->remove('sort');
            return $query;
        }

        $query->getQueryParametersContainer()->set('sort', $this->sortField);
        return $query;
    }

    /**
     * @return Sorting
     */
    public static function getEmpty()
    {
        return new Sorting(false);
    }

    /**
     * @param string $sortField
     */
    public function setSortField($sortField)
    {
        $sortField = $this->removeRelevanceSortField($sortField);
        $this->sortField = $sortField;
    }

    /**
     * Removes the relevance sort field if present in the sorting field definition.
     *
     * @param string $sorting
     * @return string
     */
    protected function removeRelevanceSortField($sorting)
    {
        $sortParameter = $sorting;
        list($sortField) = explode(' ', $sorting);
        if ($sortField === 'relevance') {
            $sortParameter = '';
            return $sortParameter;
        }

        return $sortParameter;
    }
}