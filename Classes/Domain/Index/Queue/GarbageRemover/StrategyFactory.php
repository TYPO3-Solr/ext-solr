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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class StrategyFactory
 */
class StrategyFactory
{
    /**
     * @param string $table
     * @return PageStrategy|RecordStrategy
     */
    public static function getByTable(string $table): AbstractStrategy
    {
        $isPageRelated = in_array($table, ['tt_content', 'pages']);
        return $isPageRelated
            ? GeneralUtility::makeInstance(PageStrategy::class)
            : GeneralUtility::makeInstance(RecordStrategy::class);
    }
}
