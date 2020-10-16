<?php
namespace ApacheSolrForTypo3\Solr\Utility;

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

/**
 * This utility class contains several methods for URI handling
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class UriUtility
{
    /**
     * Converts a query string into an array.
     *
     * @param string $query
     * @return array
     */
    public static function queryStringToArray(string $query): array
    {
        if ($query === '') {
            return [];
        }

        return explode('&', $query);
    }
    /**
     * Converts a query array into a string.
     *
     * @param array $query
     * @return string
     */
    public static function queryArrayToString(array $query): string
    {
        return implode('&', $query);
    }
}