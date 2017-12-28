<?php
namespace ApacheSolrForTypo3\Solr\FieldProcessor;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Daniel Poetzinger <poetzinger@aoemedia.de>
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

use ApacheSolrForTypo3\Solr\FieldProcessor\CategoryUidToHierarchy;
use ApacheSolrForTypo3\Solr\FieldProcessor\PageUidToHierarchy;
use ApacheSolrForTypo3\Solr\FieldProcessor\PathToHierarchy;
use ApacheSolrForTypo3\Solr\FieldProcessor\TimestampToIsoDate;
use ApacheSolrForTypo3\Solr\FieldProcessor\TimestampToUtcIsoDate;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service class that modifies fields in a Apache_Solr_Document, used for
 * common field processing during indexing or resolving
 *
 * @author Daniel Poetzinger <poetzinger@aoemedia.de>
 */
class Service
{

    /**
     * Modifies a list of documents
     *
     * @param \Apache_Solr_Document[] $documents
     * @param array $processingConfiguration
     */
    public function processDocuments(
        array $documents,
        array $processingConfiguration
    ) {
        foreach ($documents as $document) {
            $this->processDocument($document, $processingConfiguration);
        }
    }

    /**
     * modifies a document according to the given configuration
     *
     * @param \Apache_Solr_Document $document
     * @param array $processingConfiguration
     */
    public function processDocument(
        \Apache_Solr_Document $document,
        array $processingConfiguration
    ) {
        foreach ($processingConfiguration as $fieldName => $instruction) {
            $fieldInformation = $document->getField($fieldName);
            $isSingleValueField = false;

            if ($fieldInformation !== false) {
                $fieldValue = $fieldInformation['value'];

                if (!is_array($fieldValue)) {
                    // turn single value field into multi value field
                    $fieldValue = [$fieldValue];
                    $isSingleValueField = true;
                }

                switch ($instruction) {
                    case 'timestampToUtcIsoDate':
                        /** @var $processor TimestampToUtcIsoDate */
                        $processor = GeneralUtility::makeInstance(TimestampToUtcIsoDate::class);
                        $fieldValue = $processor->process($fieldValue);
                        break;
                    case 'timestampToIsoDate':
                        /** @var $processor TimestampToIsoDate */
                        $processor = GeneralUtility::makeInstance(TimestampToIsoDate::class);
                        $fieldValue = $processor->process($fieldValue);
                        break;
                    case 'pathToHierarchy':
                        /** @var $processor PathToHierarchy */
                        $processor = GeneralUtility::makeInstance(PathToHierarchy::class);
                        $fieldValue = $processor->process($fieldValue);
                        break;
                    case 'pageUidToHierarchy':
                        /** @var $processor PageUidToHierarchy */
                        $processor = GeneralUtility::makeInstance(PageUidToHierarchy::class);
                        $fieldValue = $processor->process($fieldValue);
                        break;
                    case 'categoryUidToHierarchy':
                        /** @var $processor CategoryUidToHierarchy */
                        $processor = GeneralUtility::makeInstance(CategoryUidToHierarchy::class);
                        $fieldValue = $processor->process($fieldValue);
                        break;
                    case 'uppercase':
                        $fieldValue = array_map('mb_strtoupper', $fieldValue);
                        break;
                }

                if ($isSingleValueField) {
                    // turn multi value field back into single value field
                    $fieldValue = $fieldValue[0];
                }

                $document->setField($fieldName, $fieldValue);
            }
        }
    }
}
