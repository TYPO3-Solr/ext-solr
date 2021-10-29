<?php

namespace ApacheSolrForTypo3\Solr\Utility;

/**
 * Copyright notice
 *
 * (c) 2020 Lars Tode <lars.tode@dkd.de>
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 */

/**
 * Utility class to sort parameters
 *
 * This class is used in places building URI for links, facets etc.
 *
 * @see \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\UrlFacetContainer::getActiveFacets
 * @see \ApacheSolrForTypo3\Solr\Domain\Search\Uri\SearchUriBuilder::getAddFacetValueUri
 */
class ParameterSortingUtility
{
    /**
     * Sort a list of parameters either by their key or value
     *
     * @param array $parameters
     * @param string $type
     * @return array
     */
    public static function sortByType(array &$parameters, string $type = 'index'): array
    {
        switch ($type) {
            case 'assoc':
                return self::sortByIndex($parameters);
            case 'index':
            default:
                return self::sortByValue($parameters);
        }
    }

    /**
     * Sort a list of parameters by their values
     *
     * @param array $parameters
     * @return array
     */
    public static function sortByValue(array &$parameters)
    {
        usort(
            $parameters,
            [self::class, 'sort']
        );
        return $parameters;
    }

    /**
     * Sort a list of parameters by their keys
     *
     * @param array $parameters
     * @return array
     */
    public static function sortByIndex(array &$parameters)
    {
        uksort(
            $parameters,
            [self::class, 'sort']
        );

        return $parameters;
    }

    /**
     * Since the sort operation does not differ between keys and values it is placed inside an own method
     *
     * @param string $a
     * @param string $b
     * @return int
     */
    public static function sort(string $a, string $b): int
    {
        return strcmp($a, $b);
    }
}
