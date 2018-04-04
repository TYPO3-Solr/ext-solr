<?php
namespace ApacheSolrForTypo3\Solr\Report;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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
     *
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
            /** @scrutinizer ignore-type */ 'allow_url_fopen',
            /** @scrutinizer ignore-type */ $value,
            /** @scrutinizer ignore-type */ $message,
            /** @scrutinizer ignore-type */ $severity
        );

        return $reports;
    }
}
