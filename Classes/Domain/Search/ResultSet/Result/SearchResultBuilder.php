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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result;

use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use InvalidArgumentException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The SearchResultBuilder is responsible to build a SearchResult object from an \ApacheSolrForTypo3\Solr\System\Solr\Document\Document
 * and should use a different class as SearchResult if configured.
 */
class SearchResultBuilder
{
    /**
     * This method is used to wrap the original solr document instance in an instance of the configured SearchResult
     * class.
     *
     * @param Document $originalDocument
     * @throws InvalidArgumentException
     * @return SearchResult
     */
    public function fromApacheSolrDocument(Document $originalDocument): SearchResult
    {
        $searchResultClassName = $this->getResultClassName();
        $result = GeneralUtility::makeInstance($searchResultClassName, /** @scrutinizer ignore-type */ $originalDocument->getFields() ?? []);

        if (!$result instanceof SearchResult) {
            throw new InvalidArgumentException('Could not create result object with class: ' . $searchResultClassName, 1470037679);
        }

        return $result;
    }

    /**
     * @return string
     */
    protected function getResultClassName(): string
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '] ?? SearchResult::class;
    }
}
