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

namespace ApacheSolrForTypo3\Solr\Utility;

use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ManagedResourcesUtility
 */
class ManagedResourcesUtility
{

    /**
     * Export synonyms in plain text format
     * @param array $synonyms
     * @return string
     */
    public static function exportSynonymsToTxt(array $synonyms) : string
    {
        if (empty($synonyms)) {
            throw new \InvalidArgumentException('Nothing to export!', 1502978329);
        }

        $contentLines = '';
        foreach ($synonyms as $synonymBaseWord => $synonymWords) {
            $contentLines .= $synonymBaseWord . ' => ' . implode(',', $synonymWords) . PHP_EOL;
        }
        return rtrim($contentLines);
    }

    /**
     * Read plain text synonym file and import these synonyms
     * @param array $synonymFileUpload
     * @return array
     */
    public static function importSynonymsFromPlainTextContents(array $synonymFileUpload) : array
    {
        $fileStream = new Stream($synonymFileUpload['tmp_name']);

        $fileLines = GeneralUtility::trimExplode(PHP_EOL, $fileStream->getContents(), true);

        $synonymList = [];
        foreach ($fileLines as $line) {
            $synonymList = self::convertSynonymFileLineForImport($line, $synonymList);
        }

        return $synonymList;
    }

    /**
     * Read plain text stopword file
     * @param array $stopwordsFileUpload
     * @return string
     */
    public static function importStopwordsFromPlainTextContents(array $stopwordsFileUpload) : string
    {
        $fileStream = new Stream($stopwordsFileUpload['tmp_name']);

        return $fileStream->getContents();
    }

    /**
     * Convert synonym file line for import
     * @param string $line
     * @param array $synonymList
     * @return array
     */
    protected static function convertSynonymFileLineForImport($line, $synonymList) : array
    {
        $lineParts = GeneralUtility::trimExplode('=>', $line, true);

        if (isset($lineParts[1])) {
            $baseWord = mb_strtolower($lineParts[0] ?? '');
            $synonyms = GeneralUtility::trimExplode(',', mb_strtolower($lineParts[1]), true);
        } else {
            $synonyms = GeneralUtility::trimExplode(',', mb_strtolower($lineParts[0]), true);
            $baseWord = mb_strtolower(reset($synonyms));
        }
        $synonymList[$baseWord] = $synonyms;

        return $synonymList;
    }
}
