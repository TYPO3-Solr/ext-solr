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

namespace ApacheSolrForTypo3\Solr\Utility;

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
