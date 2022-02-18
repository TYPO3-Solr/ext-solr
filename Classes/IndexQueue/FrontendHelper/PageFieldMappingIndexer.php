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

namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

// TODO use/extend ApacheSolrForTypo3\Solr\IndexQueue\AbstractIndexer
use ApacheSolrForTypo3\Solr\IndexQueue\AbstractIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\InvalidFieldNameException;
use ApacheSolrForTypo3\Solr\SubstitutePageIndexer;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Indexer to add / overwrite page document fields as defined in
 * plugin.tx_solr.index.queue.pages.fields.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class PageFieldMappingIndexer implements SubstitutePageIndexer
{
    /**
     * @var \ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $pageIndexingConfigurationName = 'pages';

    /**
     * @param TypoScriptConfiguration $configuration
     */
    public function __construct(TypoScriptConfiguration $configuration = null)
    {
        $this->configuration = $configuration == null ? Util::getSolrConfiguration() : $configuration;
    }

    /**
     * @param string $pageIndexingConfigurationName
     */
    public function setPageIndexingConfigurationName($pageIndexingConfigurationName)
    {
        $this->pageIndexingConfigurationName = $pageIndexingConfigurationName;
    }

    /**
     * Returns a substitute document for the currently being indexed page.
     *
     * Uses the original document and adds fields as defined in
     * plugin.tx_solr.index.queue.pages.fields.
     *
     * @param Document $pageDocument The original page document.
     * @return Document A Apache Solr Document object that replace the default page document
     */
    public function getPageDocument(Document $pageDocument)
    {
        $substitutePageDocument = clone $pageDocument;


        $mappedFields = $this->getMappedFields($pageDocument);
        foreach ($mappedFields as $fieldName => $fieldValue) {
            if (isset($substitutePageDocument->{$fieldName})) {
                // reset = overwrite, especially important to not make fields
                // multi valued where they may not accept multiple values
                unset($substitutePageDocument->{$fieldName});
            }

            // add new field / overwrite field if it was set before
            if ($fieldValue !== '' && $fieldValue !== null) {
                $substitutePageDocument->setField($fieldName, $fieldValue);
            }
        }

        return $substitutePageDocument;
    }

    /**
     * Gets the mapped fields as an array mapping field names to values.
     *
     * @throws InvalidFieldNameException
     * @param Document $pageDocument The original page document.
     * @return array An array mapping field names to their values.
     */
    protected function getMappedFields(Document $pageDocument)
    {
        $fields = [];

        $mappedFieldNames = $this->configuration->getIndexQueueMappedFieldsByConfigurationName($this->pageIndexingConfigurationName);

        foreach ($mappedFieldNames as $mappedFieldName) {
            if (!AbstractIndexer::isAllowedToOverrideField($mappedFieldName)) {
                throw new InvalidFieldNameException(
                    'Must not overwrite field "type".',
                    1435441863
                );
            }
            $fields[$mappedFieldName] = $this->resolveFieldValue($mappedFieldName, $pageDocument);
        }

        return $fields;
    }

    /**
     * Resolves a field mapping to its value depending on its configuration.
     *
     * Allows to put the page record through cObj processing if wanted / needed.
     * Otherwise the plain page record field value is used.
     *
     * @param string $solrFieldName The Solr field name to resolve the value from the item's record
     * @return string The resolved string value to be indexed
     */
    protected function resolveFieldValue($solrFieldName, Document $pageDocument)
    {
        $pageRecord = $GLOBALS['TSFE']->page;

        $pageIndexingConfiguration = $this->configuration->getIndexQueueFieldsConfigurationByConfigurationName($this->pageIndexingConfigurationName);

        if (isset($pageIndexingConfiguration[$solrFieldName . '.'])) {
            $pageRecord = AbstractIndexer::addVirtualContentFieldToRecord($pageDocument, $pageRecord);

            // configuration found => need to resolve a cObj
            $contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class, $GLOBALS['TSFE']);
            $contentObject->start($pageRecord, 'pages');

            $fieldValue = $contentObject->cObjGetSingle(
                $pageIndexingConfiguration[$solrFieldName],
                $pageIndexingConfiguration[$solrFieldName . '.']
            );

            if (AbstractIndexer::isSerializedValue($pageIndexingConfiguration, $solrFieldName)) {
                $fieldValue = unserialize($fieldValue);
            }
        } else {
            $fieldValue = $pageRecord[$pageIndexingConfiguration[$solrFieldName]];
        }

        return $fieldValue;
    }
}
