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

namespace ApacheSolrForTypo3\Solr\Middleware;

use ApacheSolrForTypo3\Solr\IndexQueue\AbstractIndexer;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Thin wrapper around AbstractIndexer to expose the field mapping method publicly.
 * Used by SolrIndexingMiddleware for record indexing in the new unified pipeline.
 */
class RecordFieldMapper extends AbstractIndexer
{
    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Public access to the parent's protected addDocumentFieldsFromTyposcript method.
     */
    public function addDocumentFieldsFromTyposcript(
        Document $document,
        array $indexingConfiguration,
        array $data,
        ServerRequest $request,
        int|SiteLanguage $language,
    ): Document {
        return parent::addDocumentFieldsFromTyposcript($document, $indexingConfiguration, $data, $request, $language);
    }
}
