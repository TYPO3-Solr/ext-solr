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

namespace ApacheSolrForTypo3\Solr\System\Logging;

use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * The DebugWriter is used to write the devLog messages to the output of the page, or to the TYPO3 console in the
 * backend to provide a simple and lightweight debugging possibility.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DebugWriter
{
    /**
     * When the feature is enabled with: plugin.tx_solr.logging.debugOutput the log writer uses the extbase
     * debug functionality in the frontend, or the console in the backend to display the devlog messages.
     *
     * @param int|string $level Log level. Value according to \TYPO3\CMS\Core\Log\LogLevel. Alternatively accepts a string.
     * @param string $message Log message.
     * @param array $data Additional data to log
     */
    public function write($level, $message, $data = [])
    {
        $debugAllowedForIp = $this->getIsAllowedByDevIPMask();
        if (!$debugAllowedForIp) {
            return;
        }

        $isDebugOutputEnabled = $this->getIsDebugOutputEnabled();
        if (!$isDebugOutputEnabled) {
            return;
        }

        $isPageIndexingRequest = $this->getIsPageIndexingRequest();
        if ($isPageIndexingRequest) {
            return;
        }

        $this->writeDebugMessage($level, $message, $data);
    }

    /**
     * @return bool
     */
    protected function getIsAllowedByDevIPMask()
    {
        return GeneralUtility::cmpIP(GeneralUtility::getIndpEnv('REMOTE_ADDR'), $GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask']);
    }

    /**
     * Check if Logging via debugOutput has been configured
     *
     * @return bool
     */
    protected function getIsDebugOutputEnabled()
    {
        return Util::getSolrConfiguration()->getLoggingDebugOutput();
    }

    protected function getIsPageIndexingRequest(): bool
    {
        if (!($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof Request) {
            return false;
        }
        return $GLOBALS['TYPO3_REQUEST']->hasHeader(PageIndexerRequest::SOLR_INDEX_HEADER);
    }

    /**
     * @param int|string $level Log level. Value according to \TYPO3\CMS\Core\Log\LogLevel. Alternatively accepts a string.
     * @param string $message Log message.
     * @param array $data Additional data to log
     */
    protected function writeDebugMessage($level, $message, $data)
    {
        $parameters = ['extKey' => 'solr', 'msg' => $message, 'level' => $level, 'data' => $data];
        $message = $parameters['msg'] ?? '';
        if (ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend()) {
            DebugUtility::debug($parameters, $parameters['extKey'], 'DevLog ext:solr: ' . $message);
        } else {
            echo $message . ':<br/>';
            DebuggerUtility::var_dump($parameters);
        }
    }
}
