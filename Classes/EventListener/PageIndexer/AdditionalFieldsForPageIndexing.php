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

namespace ApacheSolrForTypo3\Solr\EventListener\PageIndexer;

use ApacheSolrForTypo3\Solr\Event\Indexing\AfterPageDocumentIsCreatedForIndexingEvent;
use ApacheSolrForTypo3\Solr\System\ContentObject\ContentObjectService;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Additional fields indexer.
 *
 * @todo Move this to an Index Queue frontend helper
 *
 * Adds page document fields as configured in
 * plugin.tx_solr.index.additionalFields.
 */
final readonly class AdditionalFieldsForPageIndexing
{
    public function __construct(
        private ContentObjectService $contentObjectService,
    ) {}

    /**
     * Returns a substituted document for the currently being indexed page.
     */
    #[AsEventListener(
        identifier: 'solr.index.AdditionalFieldsForPageIndexing',
    )]
    public function __invoke(AfterPageDocumentIsCreatedForIndexingEvent $event): void
    {
        $originalPageDocument = $event->getDocument();
        $substitutePageDocument = clone $originalPageDocument;

        $additionalFields = $this->getAdditionalFields(
            $event->getConfiguration()->getIndexAdditionalFieldsConfiguration(),
            $event->getConfiguration()->getIndexMappedAdditionalFieldNames(),
        );

        foreach ($additionalFields as $fieldName => $fieldValue) {
            if (!isset($originalPageDocument->{$fieldName})) {
                // making sure we only _add_ new fields
                $substitutePageDocument->setField($fieldName, $fieldValue);
            }
        }

        $event->overrideDocument($substitutePageDocument);
    }

    /**
     * Gets the additional fields as an array mapping field names to values.
     */
    private function getAdditionalFields(array $additionalIndexingFields, array $additionalFieldNames): array
    {
        $additionalFields = [];

        foreach ($additionalFieldNames as $additionalFieldName) {
            $additionalFields[$additionalFieldName] = $this->getFieldValue(
                $additionalIndexingFields,
                $additionalFieldName,
            );
        }

        return $additionalFields;
    }

    /**
     * Uses the page's cObj instance to resolve the additional field's value.
     */
    private function getFieldValue(array $additionalIndexingFields, string $fieldName): string
    {
        return $this->contentObjectService->renderSingleContentObjectByArrayAndKey(
            $additionalIndexingFields,
            $fieldName,
        );
    }
}
