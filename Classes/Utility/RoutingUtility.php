<?php
namespace ApacheSolrForTypo3\Solr\Utility;
/***************************************************************
 *
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
 * A copy is found in the textfile GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * This utility class contains several functions used inside of the middleware and enhancer for routing purposes
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class RoutingUtility
{
    /**
     * Builds the hash of an inflated parameter
     * This method based on the VariableProcessor since the logic is not public
     *
     * @see \TYPO3\CMS\Core\Routing\Enhancer\VariableProcessor::addHash
     *
     * @param string $value Deflated argument path
     * @return string
     */
    public static function buildHash(string $value): string
    {
        if (strlen($value) < 31 && !preg_match('#[^\w]#', $value)) {
            return $value;
        }
        // removing one bit, e.g. for enforced route prefix `{!value}`
        $hash = substr(md5($value), 0, -1);
        // Symfony Route Compiler requires first literal to be non-integer
        if ($hash[0] === (string)(int)$hash[0]) {
            $hash[0] = str_replace(
                range('0', '9'),
                range('o', 'x'),
                $hash[0]
            );
        }

        return $hash;
    }

    /**
     * Deflate a given string with a given namespace
     * This method based on the VariableProcessor since the logic is not public
     *
     * @see \TYPO3\CMS\Core\Routing\Enhancer\VariableProcessor
     *
     * @param string $parameterName
     * @param string $namespace
     * @return string
     */
    public static function deflateString(string $parameterName, string $namespace = 'tx_solr'): string
    {
        if (!empty($namespace)) {
            $parameterName = $namespace . '/' . $parameterName;
        }
        return str_replace(
            '/',
            '__',
            $parameterName
        );
    }
}