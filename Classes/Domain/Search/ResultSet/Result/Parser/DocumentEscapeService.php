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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser;

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
    public function applyHtmlSpecialCharsOnAllFields(array $documents): array
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
                $fieldValue[$key] = htmlspecialchars($fieldValueItem,  ENT_COMPAT, 'UTF-8', false);
            }
        } else {
            $fieldValue = htmlspecialchars($fieldValue, ENT_COMPAT, 'UTF-8', false);
        }

        return $fieldValue;
    }
}
