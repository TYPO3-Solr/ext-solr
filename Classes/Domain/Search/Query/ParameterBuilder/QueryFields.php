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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

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
