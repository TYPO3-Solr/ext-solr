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
 * There's a buggy PHP version in Ubuntu LTS 10.04 which causes filter_var to
 * produces incorrect results. This status checks for this issue.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class FilterVarStatus extends AbstractSolrStatus
{

    /**
     * Checks whether allow_url_fopen is enabled.
     *
     */
    public function getStatus()
    {
        $reports = [];

        $validUrl = 'http://www.typo3-solr.com';
        if (!filter_var($validUrl, FILTER_VALIDATE_URL)) {
            $message = 'You are using a PHP version that is affected by a bug in
				function filter_var(). This bug causes said function to
				incorrectly report valid URLs as invalid if they contain a
				dash (-). EXT:solr uses this function to validate URLs when
				indexing TYPO3 pages. Please check with your administrator
				whether a newer version can be installed.
				More information is available at
				<a href="https://bugs.php.net/bug.php?id=51192">php.net</a>.';

            $reports[] = GeneralUtility::makeInstance(Status::class,
                'PHP filter_var() bug',
                'Affected PHP version detected.',
                $message,
                Status::ERROR
            );
        }

        return $reports;
    }
}
