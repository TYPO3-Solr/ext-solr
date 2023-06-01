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

namespace ApacheSolrForTypo3\SolrFakeExtension3\EventListeners;

use ApacheSolrForTypo3\Solr\Event\Indexing\ModifyDocumentsBeforeIndexingEvent;

final class TestModificationOfDocuments
{
    /**
     * Allows Modification of the Documents before they go into index
     */
    public function __invoke(ModifyDocumentsBeforeIndexingEvent $event): void
    {
        foreach ($event->getDocuments() as $document) {
            $document->addField('postProcessorField_stringS', 'postprocessed');
        }
    }
}
