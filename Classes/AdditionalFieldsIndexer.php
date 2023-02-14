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

namespace ApacheSolrForTypo3\Solr;

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\ContentObject\ContentObjectService;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Additional fields indexer.
 *
 * @todo Move this to an Index Queue frontend helper
 *
 * Adds page document fields as configured in
 * plugin.tx_solr.index.additionalFields.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class AdditionalFieldsIndexer implements SubstitutePageIndexer
{
    /**
     * @var TypoScriptConfiguration
     */
    protected TypoScriptConfiguration $configuration;

    /**
     * @var array
     */
    protected array $additionalIndexingFields = [];

    /**
     * @var array
     */
    protected array $additionalFieldNames = [];

    /**
     * @var ContentObjectService
     */
    protected ContentObjectService $contentObjectService;

    /**
     * @param TypoScriptConfiguration|null $configuration
     * @param ContentObjectService|null $contentObjectService
     */
    public function __construct(
        TypoScriptConfiguration $configuration = null,
        ContentObjectService $contentObjectService = null
    ) {
        $this->configuration = $configuration ?? Util::getSolrConfiguration();
        $this->additionalIndexingFields = $this->configuration->getIndexAdditionalFieldsConfiguration();
        $this->additionalFieldNames = $this->configuration->getIndexMappedAdditionalFieldNames();
        $this->contentObjectService = $contentObjectService ?? GeneralUtility::makeInstance(ContentObjectService::class);
    }

    /**
     * Returns a substitute document for the currently being indexed page.
     *
     * Uses the original document and adds fields as defined in
     * plugin.tx_solr.index.additionalFields.
     *
     * @param Document $originalPageDocument The original page document.
     * @return Document A Apache Solr Document object that replace the default page document
     */
    public function getPageDocument(Document $originalPageDocument): Document
    {
        $substitutePageDocument = clone $originalPageDocument;
        $additionalFields = $this->getAdditionalFields();

        foreach ($additionalFields as $fieldName => $fieldValue) {
            if (!isset($originalPageDocument->{$fieldName})) {
                // making sure we only _add_ new fields
                $substitutePageDocument->setField($fieldName, $fieldValue);
            }
        }

        return $substitutePageDocument;
    }

    /**
     * Gets the additional fields as an array mapping field names to values.
     *
     * @return array An array mapping additional field names to their values.
     */
    protected function getAdditionalFields(): array
    {
        $additionalFields = [];

        foreach ($this->additionalFieldNames as $additionalFieldName) {
            $additionalFields[$additionalFieldName] = $this->getFieldValue($additionalFieldName);
        }

        return $additionalFields;
    }

    /**
     * Uses the page's cObj instance to resolve the additional field's value.
     *
     * @param string $fieldName The name of the field to get.
     * @return string The field's value.
     */
    protected function getFieldValue(string $fieldName): string
    {
        return $this->contentObjectService->renderSingleContentObjectByArrayAndKey($this->additionalIndexingFields, $fieldName);
    }
}
