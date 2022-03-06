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

namespace ApacheSolrForTypo3\Solr\Report;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report about whether the php.ini setting allow_url_fopen
 * is activated or not.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class AllowUrlFOpenStatus extends AbstractSolrStatus
{
    /**
     * Checks whether allow_url_fopen is enabled.
     * @noinspection PhpMissingReturnTypeInspection
     *
     * @noinspection PhpMissingReturnTypeInspection see {@link \TYPO3\CMS\Reports\StatusProviderInterface::getStatus()}
     */
    public function getStatus()
    {
        $reports = [];
        $severity = Status::OK;
        $value = 'On';
        $message = '';

        if (!ini_get('allow_url_fopen')) {
            $severity = Status::ERROR;
            $value = 'Off';
            $message = 'allow_url_fopen must be enabled in php.ini to allow
				communication between TYPO3 and the Apache Solr server.
				Indexing pages using the Index Queue will also not work with
				this setting disabled.';
        }

        $reports[] = GeneralUtility::makeInstance(
            Status::class,
            /** @scrutinizer ignore-type */
            'allow_url_fopen',
            /** @scrutinizer ignore-type */
            $value,
            /** @scrutinizer ignore-type */
            $message,
            /** @scrutinizer ignore-type */
            $severity
        );

        return $reports;
    }
}
