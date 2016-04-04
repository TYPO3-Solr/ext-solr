<?php
namespace ApacheSolrForTypo3\Solr\Plugin\Results;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Plugin\CommandPluginAware;
use ApacheSolrForTypo3\Solr\Plugin\CommandPluginBase;
use ApacheSolrForTypo3\Solr\Plugin\PluginCommand;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Errors command class to render error messages for errors that may have
 * occurred during searching.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class ErrorsCommand implements PluginCommand
{

    /**
     * Parent plugin
     *
     * @var Results
     */
    protected $parentPlugin;

    /**
     * Configuration
     *
     * @var array
     */
    protected $configuration;

    /**
     * Constructor.
     *
     * @param CommandPluginBase $parentPlugin Parent plugin object.
     */
    public function __construct(CommandPluginBase $parentPlugin)
    {
        $this->parentPlugin = $parentPlugin;
        $this->configuration = $parentPlugin->typoScriptConfiguration;
    }

    /**
     * Provides the values for the markers in the errors template subpart.
     *
     * @return array Array of markers in the errors template subpart
     */
    public function execute()
    {
        $marker = array();

        $errors = $this->getErrors();
        if (!empty($errors)) {
            $marker['loop_errors|error'] = $errors;
        } else {
            $marker = null;
        }

        return $marker;
    }

    /**
     * Gets errors that may have been found with the user's query.
     *
     * @return array An array of errors, each error is an array itself and has a message and a code key.
     */
    protected function getErrors()
    {
        $errors = array();

        // detect empty user queries
        $resultService = $this->parentPlugin->getSearchResultSetService();
        $searchWasTriggeredWithEmptyQuery = $resultService->getLastSearchWasExecutedWithEmptyQueryString();

        if ($searchWasTriggeredWithEmptyQuery && !$this->configuration->getSearchQueryAllowEmptyQuery()) {
            $errors[] = array(
                'message' => '###LLL:error_emptyQuery###',
                'code' => 1300893669
            );
        }

        // hook to provide additional error messages
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['addSearchErrors'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['addSearchErrors'] as $classReference) {
                $errorDetector = GeneralUtility::getUserObj($classReference);

                if ($errorDetector instanceof ErrorDetector) {
                    if ($errorDetector instanceof CommandPluginAware) {
                        $errorDetector->setParentPlugin($this->parentPlugin);
                    }

                    $additionalErrors = $errorDetector->getErrors();

                    if (is_array($additionalErrors)) {
                        $errors = array_merge($errors, $additionalErrors);
                    } else {
                        throw new \UnexpectedValueException($classReference . ' must return an array',
                            1359156111);
                    }
                } else {
                    throw new \InvalidArgumentException(
                        'Error detector "' . $classReference . '" must implement interface ApacheSolrForTypo3\Solr\Plugin\Results\ErrorDetector.',
                        1359156192
                    );
                }
            }
        }

        return $errors;
    }
}
