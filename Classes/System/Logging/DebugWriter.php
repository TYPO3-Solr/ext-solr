<?php

namespace ApacheSolrForTypo3\Solr\System\Logging;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2016 Timo Hund <timo.hund@dkd.de
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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

use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * The DebugWriter is used to write the devLog messages to the output of the page, or to the TYPO3 console in the
 * backend to provide a simple and lightweigt debugging possibility.
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

    /**
     * @param int|string $level Log level. Value according to \TYPO3\CMS\Core\Log\LogLevel. Alternatively accepts a string.
     * @param string $message Log message.
     * @param array $data Additional data to log
     */
    protected function writeDebugMessage($level, $message, $data)
    {
        $parameters = ['extKey' => 'solr', 'msg' => $message, 'level' => $level, 'data' => $data];
        $message = isset($parameters['msg']) ? $parameters['msg'] : '';
        if (TYPO3_MODE === 'BE') {
            DebugUtility::debug($parameters, $parameters['extKey'], 'DevLog ext:solr: ' . $message);
        } else {
            echo $message . ':<br/>';
            DebuggerUtility::var_dump($parameters);
        }
    }
}
