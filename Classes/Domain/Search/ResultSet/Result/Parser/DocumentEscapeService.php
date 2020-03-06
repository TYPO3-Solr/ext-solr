<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\Util;

/**
 * Applies htmlspecialschars on documents of a solr response.
 */
class DocumentEscapeService {

    /**
     * @var TypoScriptConfiguration
     */
    protected $typoScriptConfiguration = null;

    /**
     * DocumentEscapeService constructor.
     * @param TypoScriptConfiguration|null $typoScriptConfiguration
     */
    public function __construct(TypoScriptConfiguration $typoScriptConfiguration = null) {
        $this->typoScriptConfiguration = $typoScriptConfiguration ?? Util::getSolrConfiguration();
    }

    /**
     * This method is used to apply htmlspecialchars on all document fields that
     * are not configured to be secure. Secure mean that we know where the content is coming from.
     *
     * @param Document[] $documents
     * @return Document[]
     */
    public function applyHtmlSpecialCharsOnAllFields(array $documents)
    {
        $trustedSolrFields = $this->typoScriptConfiguration->getSearchTrustedFieldsArray();

        foreach ($documents as $key => $document) {
            $fieldNames = array_keys($document->getFields() ?? []);

            foreach ($fieldNames as $fieldName) {
                if (is_array($trustedSolrFields) && in_array($fieldName, $trustedSolrFields)) {
                    // we skip this field, since it was marked as secure
                    continue;
                }

                $value = $this->applyHtmlSpecialCharsOnSingleFieldValue($document[$fieldName]);
                $document->setField($fieldName, $value);
            }

            $documents[$key] = $document;
        }

        return $documents;
    }

    /**
     * Applies htmlspecialchars on all items of an array of a single value.
     *
     * @param $fieldValue
     * @return array|string
     */
    protected function applyHtmlSpecialCharsOnSingleFieldValue($fieldValue)
    {
        if (is_array($fieldValue)) {
            foreach ($fieldValue as $key => $fieldValueItem) {
                $fieldValue[$key] = htmlspecialchars($fieldValueItem, null, null, false);
            }
        } else {
            $fieldValue = htmlspecialchars($fieldValue, null, null, false);
        }

        return $fieldValue;
    }
}
