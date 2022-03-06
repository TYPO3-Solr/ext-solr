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

use TYPO3\CMS\Core\Log\Logger;
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
     * @var Logger|null
     */
    protected ?Logger $logger = null;

    /**
     * @var DebugWriter
     */
    protected $debugWriter;

    /**
     * @var string
     */
    protected string $className = '';

    /**
     * SolrLogManager constructor.
     *
     * @param string $className
     * @param DebugWriter|null $debugWriter
     */
    public function __construct(string $className, DebugWriter $debugWriter = null)
    {
        $this->className = $className;
        $this->debugWriter = $debugWriter ?? GeneralUtility::makeInstance(DebugWriter::class);
    }

    /**
     * @return Logger
     */
    protected function getLogger(): Logger
    {
        if ($this->logger === null) {
            $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger($this->className);
        }

        return $this->logger;
    }

    /**
     * Adds an entry to the LogManager
     *
     * @param int|string $level Log level. Value according to \TYPO3\CMS\Core\Log\LogLevel. Alternatively accepts a string.
     * @param string $message Log message.
     * @param array $data Additional data to log
     */
    public function log($level, string $message, array $data = [])
    {
        $this->getLogger()->log($level, $message, $data);
        $this->debugWriter->write($level, $message, $data);
    }
}
