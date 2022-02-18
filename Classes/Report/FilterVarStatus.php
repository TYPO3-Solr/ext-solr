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

namespace ApacheSolrForTypo3\Solr\Report;

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
