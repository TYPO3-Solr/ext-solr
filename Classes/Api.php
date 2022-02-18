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

namespace ApacheSolrForTypo3\Solr;

/**
 * Remote API related methods
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Api
{

    /**
     * Checks whether a string is a valid API key.
     *
     * @param string $apiKey API key to check for validity
     * @return bool TRUE if the API key is valid, FALSE otherwise
     */
    public static function isValidApiKey($apiKey)
    {
        return ($apiKey === self::getApiKey());
    }

    /**
     * Generates the API key for the REST API
     *
     * @return string API key for this installation
     */
    public static function getApiKey()
    {
        return sha1(
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] .
            'tx_solr_api'
        );
    }
}
