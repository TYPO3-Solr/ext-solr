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
 * The ReturnFields class is responsible to hold a list of field names that should be returned from
 * solr.
 */
class ReturnFields implements ParameterBuilder
{

    /**
     * @var array
     */
    protected $fieldList = [];

    /**
     * FieldList constructor.
     *
     * @param array $fieldList
     */
    public function __construct(array $fieldList = [])
    {
        $this->fieldList = $fieldList;
    }

    /**
     * Adds a field to the list of fields to return. Also checks whether * is
     * set for the fields, if so it's removed from the field list.
     *
     * @param string $fieldName Name of a field to return in the result documents
     */
    public function add($fieldName)
    {
        if (strpos($fieldName, '[') === false && strpos($fieldName, ']') === false && in_array('*', $this->fieldList)) {
            $this->fieldList = array_diff($this->fieldList, ['*']);
        }

        $this->fieldList[] = $fieldName;
    }

    /**
     * Removes a field from the list of fields to return (fl parameter).
     *
     * @param string $fieldName Field to remove from the list of fields to return
     */
    public function remove($fieldName)
    {
        $key = array_search($fieldName, $this->fieldList);

        if ($key !== false) {
            unset($this->fieldList[$key]);
        }
    }

    /**
     * @param string $delimiter
     * @return string
     */
    public function toString($delimiter = ',')
    {
        return implode($delimiter, $this->fieldList);
    }

    /**
     * @param string $fieldList
     * @param string $delimiter
     * @return ReturnFields
     */
    public static function fromString($fieldList, $delimiter = ',')
    {
        $fieldListArray = GeneralUtility::trimExplode($delimiter, $fieldList);
        return static::fromArray($fieldListArray);
    }

    /**
     * @param array $fieldListArray
     * @return ReturnFields
     */
    public static function fromArray(array $fieldListArray)
    {
        return new ReturnFields($fieldListArray);
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return array_unique(array_values($this->fieldList));
    }

    /**
     * @param AbstractQueryBuilder $parentBuilder
     * @return AbstractQueryBuilder
     */
    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $parentBuilder->getQuery()->setFields($this->getValues());
        return $parentBuilder;
    }
}
