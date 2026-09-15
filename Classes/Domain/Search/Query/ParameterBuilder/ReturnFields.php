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
class ReturnFields implements ParameterBuilderInterface
{
    protected array $fieldList = [];

    /**
     * FieldList constructor.
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
    public function add(string $fieldName): void
    {
        if (!str_contains($fieldName, '[') && !str_contains($fieldName, ']') && in_array('*', $this->fieldList)) {
            $this->fieldList = array_diff($this->fieldList, ['*']);
        }

        $this->fieldList[] = $fieldName;
    }

    /**
     * Removes a field from the list of fields to return (fl parameter).
     *
     * @param string $fieldName Field to remove from the list of fields to return
     */
    public function remove(string $fieldName): void
    {
        $key = array_search($fieldName, $this->fieldList);

        if ($key !== false) {
            unset($this->fieldList[$key]);
        }
    }

    public function toString(string $delimiter = ','): string
    {
        return implode($delimiter, $this->fieldList);
    }

    public static function fromString(string $fieldList, string $delimiter = ','): ReturnFields
    {
        $fieldListArray = GeneralUtility::trimExplode($delimiter, $fieldList);
        return static::fromArray($fieldListArray);
    }

    public static function fromArray(array $fieldListArray): ReturnFields
    {
        return new ReturnFields($fieldListArray);
    }

    public function getValues(): array
    {
        return array_unique(array_values($this->fieldList));
    }

    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $parentBuilder->getQuery()->setFields($this->getValues());
        return $parentBuilder;
    }
}
