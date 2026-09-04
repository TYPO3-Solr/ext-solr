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

namespace ApacheSolrForTypo3\Solr\Traits;

use TYPO3\CMS\Core\Exception\Page\PageNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

trait SkipRecordByRootlineConfigurationTrait
{
    /**
     * Check if at least one page in the record's rootline is configured to exclude sub-entries from indexing
     */
    protected function skipRecordByRootlineConfiguration(int $pid): bool
    {
        /** @var RootlineUtility $rootlineUtility */
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pid);
        try {
            $rootline = $rootlineUtility->get();
        } catch (PageNotFoundException) {
            return true;
        }
        foreach ($rootline as $page) {
            if (isset($page['no_search_sub_entries']) && $page['no_search_sub_entries'] && $pid !== $page['uid']) {
                return true;
            }
        }
        return false;
    }
}
