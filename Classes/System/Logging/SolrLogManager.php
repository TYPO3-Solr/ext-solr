<?php

namespace ApacheSolrForTypo3\Solr\System\Logging;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 - Thomas Hohn <tho@systime.dk>
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

use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Wrapper to for the TYPO3 Logging Framework
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class SolrLogManager
{
    const WARNING = LogLevel::WARNING;
    const ERROR = LogLevel::ERROR;
    const INFO = LogLevel::INFO;
    const NOTICE = LogLevel::NOTICE;

    /**
     * @var \TYPO3\CMS\Core\Log\Logger
     */
    protected $logger = null;

    /**
     * SolrLogManager constructor.
     *
     * @param $className
     */
    public function __construct($className)
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger($className);
    }

    /**
     * Adds an entry to the LogManager
     *
     * @param int|string $level Log level. Value according to \TYPO3\CMS\Core\Log\LogLevel. Alternatively accepts a string.
     * @param string $message Log message.
     * @param array $data Additional data to log
     *
     * @return mixed
     */
    public function log($level, $message, array $data = [])
    {
        $this->logger->log($level, $message, $data);
    }
}
