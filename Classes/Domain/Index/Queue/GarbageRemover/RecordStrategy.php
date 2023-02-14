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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover;

use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Class RecordStrategy
 */
class RecordStrategy extends AbstractStrategy
{
    /**
     * Removes the garbage of a record.
     *
     * @param string $table
     * @param int $uid
     */
    protected function removeGarbageOfByStrategy(string $table, int $uid)
    {
        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? false;
        $transOrigPointerField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] ?? false;
        $isTableTranslatable = $languageField && $transOrigPointerField;

        if ($isTableTranslatable) {
            $record = BackendUtility::getRecord($table, $uid, implode(',', [$languageField, $transOrigPointerField]), '', false);
            $isRecordATranslation = $record && $record[$languageField] > 0 && $record[$transOrigPointerField] > 0;
            if ($isRecordATranslation) {
                $this->deleteIndexDocuments($table, (int)$record[$transOrigPointerField], (int)$record[$languageField]);
                return;
            }
        }

        $this->deleteInSolrAndRemoveFromIndexQueue($table, $uid);
    }
}
