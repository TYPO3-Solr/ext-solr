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

namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

// TODO use/extend ApacheSolrForTypo3\Solr\IndexQueue\AbstractIndexer
use ApacheSolrForTypo3\Solr\Event\Indexing\AfterPageDocumentIsCreatedForIndexingEvent;
use ApacheSolrForTypo3\Solr\IndexQueue\AbstractIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\InvalidFieldNameException;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Indexer to add / overwrite page document fields as defined in
 * plugin.tx_solr.index.queue.pages.fields.
 */
class PageFieldMappingIndexer
{
    protected TypoScriptConfiguration $configuration;
    protected string $pageIndexingConfigurationName = 'pages';

    /**
     * Builds a substitute document for the currently being indexed page.
     *
     * Uses the original document and adds fields as defined in
     * plugin.tx_solr.index.queue.pages.fields.
     */
    public function __invoke(AfterPageDocumentIsCreatedForIndexingEvent $event): void
    {
        $substitutePageDocument = clone $event->getDocument();
        $this->configuration = $event->getConfiguration();
        $this->pageIndexingConfigurationName = $event->getIndexingConfigurationName();

        $mappedFields = $this->getMappedFields($event->getDocument(), $event->getRecord());
        foreach ($mappedFields as $fieldName => $fieldValue) {
            if (isset($substitutePageDocument->{$fieldName})) {
                // reset = overwrite, especially important to not make fields
                // multivalued where they may not accept multiple values
                unset($substitutePageDocument->{$fieldName});
            }

            // add new field / overwrite field if it was set before
            if ($fieldValue !== '' && $fieldValue !== null) {
                $substitutePageDocument->setField($fieldName, $fieldValue);
            }
        }

        $event->overrideDocument($substitutePageDocument);
    }

    /**
     * Gets the mapped fields as an array mapping field names to values.
     *
     * @param Document $pageDocument The original page document.
     * @return array An array mapping field names to their values.
     *
     * @throws InvalidFieldNameException
     */
    protected function getMappedFields(Document $pageDocument, array $pageRecord): array
    {
        $fields = [];

        $mappedFieldNames = $this->configuration->getIndexQueueMappedFieldsByConfigurationName($this->pageIndexingConfigurationName);

        foreach ($mappedFieldNames as $mappedFieldName) {
            if (!AbstractIndexer::isAllowedToOverrideField($mappedFieldName)) {
                throw new InvalidFieldNameException(
                    'Must not overwrite field "type".',
                    1435441863,
                );
            }
            $fields[$mappedFieldName] = $this->resolveFieldValue($mappedFieldName, $pageDocument, $pageRecord);
        }

        return $fields;
    }

    /**
     * Resolves a field mapping to its value depending on its configuration.
     *
     * Allows to put the page record through cObj processing if wanted / needed.
     * Otherwise, the plain page record field value is used.
     *
     * @param string $solrFieldName The Solr field name to resolve the value from the item's record
     * @return array|float|int|string|null The resolved value to be indexed
     */
    protected function resolveFieldValue(
        string $solrFieldName,
        Document $pageDocument,
        array $pageRecord,
    ): mixed {
        $pageIndexingConfiguration = $this->configuration->getIndexQueueFieldsConfigurationByConfigurationName($this->pageIndexingConfigurationName);

        if (isset($pageIndexingConfiguration[$solrFieldName . '.'])) {
            $pageRecord = AbstractIndexer::addVirtualContentFieldToRecord($pageDocument, $pageRecord);

            // configuration found => need to resolve a cObj
            $contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            $contentObject->start($pageRecord, 'pages');

            $fieldValue = $contentObject->cObjGetSingle(
                $pageIndexingConfiguration[$solrFieldName],
                $pageIndexingConfiguration[$solrFieldName . '.'],
            );

            try {
                $unserializedFieldValue = @unserialize($fieldValue);
                if (is_array($unserializedFieldValue) || is_object($unserializedFieldValue)) {
                    $fieldValue = $unserializedFieldValue;
                }
            } catch (Throwable) {
                // Evil catch, but anyway do nothing to prevent fluting the logs on indexing.
                // If the cObject implementation do not provide data the fields are not present in index, which will be noticed and fixed by devs/integrators.
            }
        } else {
            $fieldValue = $pageRecord[$pageIndexingConfiguration[$solrFieldName]] ?? null;
        }

        return $fieldValue;
    }
}
