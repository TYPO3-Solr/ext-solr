<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The AbstractFieldList class
 */
abstract class AbstractFieldList implements ParameterBuilder
{
    /**
     * @var array
     */
    protected $fieldList = [];

    /**
     * Parameter key which should be used for Apache Solr URL query
     *
     * @var string
     */
    protected $parameterKey = '';

    /**
     * FieldList parameter builder constructor.
     *
     * private constructor should only be created with the from* methods
     *
     * @param array $fieldList
     */
    private function __construct(array $fieldList)
    {
        $this->fieldList = $fieldList;
    }

    /**
     * @param string $fieldName
     * @param float $boost
     *
     * @return ParameterBuilder
     */
    public function add(string $fieldName, float $boost = 1.0): ParameterBuilder
    {
        $this->fieldList[$fieldName] = (float)$boost;
        return $this;
    }

    /**
     * @return array
     */
    public function build() : array
    {
        $string = $this->toString();
        // return empty array on empty string
        return trim($string) === '' ? [] : [$this->parameterKey => $string];
    }

    /**
     * Creates the string representation
     *
     * @param string $delimiter
     * @return string
     */
    public function toString(string $delimiter = ' ')
    {
        $fieldListString = '';

        foreach ($this->fieldList as $fieldName => $fieldBoost) {
            $fieldListString .= $fieldName;

            if ($fieldBoost != 1.0) {
                $fieldListString .= '^' . number_format($fieldBoost, 1, '.', '');
            }

            $fieldListString .= $delimiter;
        }

        return rtrim($fieldListString, $delimiter);
    }

    /**
     * Parses the string representation of the fieldList (e.g. content^100, title^10) to the object representation.
     *
     * @param string $fieldListString
     * @param string $delimiter
     * @return AbstractFieldList
     */
    protected static function initializeFromString(string $fieldListString, string $delimiter = ',') : AbstractFieldList
    {
        $fields = GeneralUtility::trimExplode($delimiter, $fieldListString, true);
        $fieldList = [];

        foreach ($fields as $field) {
            $fieldNameAndBoost = explode('^', $field);

            $boost = 1.0;
            if (isset($fieldNameAndBoost[1])) {
                $boost = floatval($fieldNameAndBoost[1]);
            }

            $fieldName = $fieldNameAndBoost[0];
            $fieldList[$fieldName] = $boost;
        }

        return new static($fieldList);
    }
}
