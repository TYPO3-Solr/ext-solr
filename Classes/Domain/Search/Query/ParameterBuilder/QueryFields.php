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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\AbstractQueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The QueryFields class holds all information for the query which fields should be used to query (Solr qf parameter).
 */
class QueryFields implements ParameterBuilder
{

    /**
     * @var array
     */
    protected $queryFields = [];

    /**
     * QueryFields constructor.
     *
     * @param array $queryFields
     */
    public function __construct(array $queryFields = [])
    {
        $this->queryFields = $queryFields;
    }

    /**
     * @param string $fieldName
     * @param float $boost
     */
    public function set($fieldName, $boost = 1.0)
    {
        $this->queryFields[$fieldName] = (float)$boost;
    }

    /**
     * Creates the string representation
     *
     * @param string $delimiter
     * @return string
     */
    public function toString($delimiter = ' ') {
        $queryFieldString = '';

        foreach ($this->queryFields as $fieldName => $fieldBoost) {
            $queryFieldString .= $fieldName;

            if ($fieldBoost != 1.0) {
                $queryFieldString .= '^' . number_format($fieldBoost, 1, '.', '');
            }

            $queryFieldString .= $delimiter;
        }

        return rtrim($queryFieldString, $delimiter);
    }

    /**
     * Parses the string representation of the queryFields (e.g. content^100, title^10) to the object representation.
     *
     * @param string $queryFieldsString
     * @param string $delimiter
     * @return QueryFields
     */
    public static function fromString($queryFieldsString, $delimiter = ',') {
        $fields = GeneralUtility::trimExplode($delimiter, $queryFieldsString, true);
        $queryFields = [];

        foreach ($fields as $field) {
            $fieldNameAndBoost = explode('^', $field);

            $boost = 1.0;
            if (isset($fieldNameAndBoost[1])) {
                $boost = floatval($fieldNameAndBoost[1]);
            }

            $fieldName = $fieldNameAndBoost[0];
            $queryFields[$fieldName] = $boost;
        }

        return new QueryFields($queryFields);
    }

    /**
     * @param AbstractQueryBuilder $parentBuilder
     * @return AbstractQueryBuilder
     */
    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $parentBuilder->getQuery()->getEDisMax()->setQueryFields($this->toString());
        return $parentBuilder;
    }
}
