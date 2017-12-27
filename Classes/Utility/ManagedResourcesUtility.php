<?php
namespace ApacheSolrForTypo3\Solr\Utility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
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
            $baseWord = mb_strtolower($lineParts[0]);
            $synonyms = GeneralUtility::trimExplode(',', mb_strtolower($lineParts[1]), true);
        } else {
            $synonyms = GeneralUtility::trimExplode(',', mb_strtolower($lineParts[0]), true);
            $baseWord = mb_strtolower(reset($synonyms));
        }
        $synonymList[$baseWord] = $synonyms;

        return $synonymList;
    }
}
