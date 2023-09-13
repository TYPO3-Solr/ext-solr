<?php

declare(strict_types=1);

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

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Wrapper to for the TYPO3 Logging Framework
 */
class SolrLogManager implements LoggerInterface
{
    use LoggerTrait;

    protected ?Logger $logger = null;

    protected DebugWriter $debugWriter;

    protected string $className;

    public function __construct(string $className, DebugWriter $debugWriter = null)
    {
        $this->className = $className;
        $this->debugWriter = $debugWriter ?? GeneralUtility::makeInstance(DebugWriter::class);
    }

    protected function getLogger(): Logger
    {
        if ($this->logger === null) {
            $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger($this->className);
        }

        return $this->logger;
    }

    /**
     * Logs with an arbitrary level.
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->getLogger()->log($level, $message, $context);
        $this->debugWriter->write($level, $message, $context);
    }
}
