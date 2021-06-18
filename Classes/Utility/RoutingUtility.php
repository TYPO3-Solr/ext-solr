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