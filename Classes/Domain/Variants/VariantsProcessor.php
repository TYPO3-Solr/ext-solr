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

namespace ApacheSolrForTypo3\Solr\Domain\Variants;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetProcessor;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Builds the SearchResult objects from the solr response and assigns the created child SearchResult objects (the variants)
 * to the parent search result object.
 */
class VariantsProcessor implements SearchResultSetProcessor
{
    /**
     * @var TypoScriptConfiguration
     */
    protected $typoScriptConfiguration;

    /**
     * @var SearchResultBuilder
     */
    protected $resultBuilder;

    /**
     * VariantsProcessor constructor.
     * @param TypoScriptConfiguration $configuration
     * @param SearchResultBuilder|null $resultBuilder
     */
    public function __construct(TypoScriptConfiguration $configuration, SearchResultBuilder $resultBuilder = null)
    {
        $this->typoScriptConfiguration = $configuration;
        $this->resultBuilder = $resultBuilder ?? GeneralUtility::makeInstance(SearchResultBuilder::class);
    }

    /**
     * This method is used to add documents to the expanded documents of the SearchResult
     * when collapsing is configured.
     *
     * @param SearchResultSet $resultSet
     * @return SearchResultSet
     */
    public function process(SearchResultSet $resultSet)
    {
        $response = $resultSet->getResponse();
        // @extensionScannerIgnoreLine
        if (!is_array($response->response->docs ?? null)) {
            return $resultSet;
        }

        if (!$this->typoScriptConfiguration->getSearchVariants()) {
            return $resultSet;
        }

        $variantsField = $this->typoScriptConfiguration->getSearchVariantsField();
        foreach ($resultSet->getSearchResults() as $resultDocument) {
            /** @var $resultDocument SearchResult */
            $variantId = $resultDocument[$variantsField] ?? null;

            // when there is no value in the collapsing field, we can return
            if ($variantId === null) {
                continue;
            }

            $resultDocument->setVariantFieldValue($variantId);
            if (!isset($response->{'expanded'}) || !isset($response->{'expanded'}->{$variantId})) {
                continue;
            }

            $this->buildVariantDocumentAndAssignToParentResult($response, $variantId, $resultDocument);
            $resultDocument->setVariantsNumFound($response->{'expanded'}->{$variantId}->{'numFound'});
        }

        return $resultSet;
    }

    /**
     * Build the SearchResult of the variant and assigns it to the parent result document.
     *
     * @param ResponseAdapter $response
     * @param string $variantAccessKey
     * @param SearchResult $resultDocument
     */
    protected function buildVariantDocumentAndAssignToParentResult(ResponseAdapter $response, $variantAccessKey, SearchResult $resultDocument)
    {
        foreach ($response->{'expanded'}->{$variantAccessKey}->{'docs'} as $variantDocumentArray) {
            $fields = get_object_vars($variantDocumentArray);
            $variantDocument = new SearchResult($fields);

            $variantSearchResult = $this->resultBuilder->fromApacheSolrDocument($variantDocument);
            $variantSearchResult->setIsVariant(true);
            $variantSearchResult->setVariantParent($resultDocument);

            $resultDocument->addVariant($variantSearchResult);
        }
    }

}
