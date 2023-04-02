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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The AbstractFieldList class
 */
abstract class AbstractFieldList extends AbstractDeactivatable
{
    protected array $fieldList = [];

    /**
     * FieldList parameter builder constructor.
     */
    public function __construct($isEnabled, array $fieldList = [])
    {
        $this->isEnabled = $isEnabled;
        $this->fieldList = $fieldList;
    }

    protected static function buildFieldList(string $fieldListString, string $delimiter): array
    {
        $fields = GeneralUtility::trimExplode($delimiter, $fieldListString, true);
        $fieldList = [];

        foreach ($fields as $field) {
            $fieldNameAndBoost = explode('^', $field);

            $boost = 1.0;
            if (isset($fieldNameAndBoost[1])) {
                $boost = (float)($fieldNameAndBoost[1]);
            }

            $fieldName = $fieldNameAndBoost[0];
            $fieldList[$fieldName] = $boost;
        }
        return $fieldList;
    }

    public function add(string $fieldName, float $boost = 1.0): AbstractFieldList
    {
        $this->fieldList[$fieldName] = $boost;
        return $this;
    }

    /**
     * Creates the string representation
     */
    public function toString(string $delimiter = ' '): string
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
}
