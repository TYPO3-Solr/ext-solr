<?php

namespace ApacheSolrForTypo3\Solr\Domain\Variants;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017- Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetProcessor;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Builds the SearchResult objects from the solr response and assigns the created child SearchResult objects (the variants)
 * to the parent search result object.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Variants
 */
class VariantsProcessor implements SearchResultSetProcessor {

    /**
     * @var TypoScriptConfiguration
     */
    protected $typoScriptConfiguration;

    /**
     * @var SearchResultBuilder|null
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
        $this->resultBuilder = is_null($resultBuilder) ? GeneralUtility::makeInstance(SearchResultBuilder::class) : $resultBuilder;
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
        if (!is_array($response->response->docs)) {
            return $resultSet;
        }

        if (!$this->typoScriptConfiguration->getSearchVariants()) {
            return $resultSet;
        }

        $variantsField = $this->typoScriptConfiguration->getSearchVariantsField();
        foreach ($resultSet->getSearchResults() as $key => $resultDocument) {
            /** @var $resultDocument SearchResult */
            $variantField = $resultDocument->getField($variantsField);
            $variantId = isset($variantField['value']) ? $variantField['value'] : null;

            // when there is no value in the collapsing field, we can return
            if ($variantId === null) {
                continue;
            }

            $variantAccessKey = mb_strtolower($variantId);
            if (!isset($response->{'expanded'}) || !isset($response->{'expanded'}->{$variantAccessKey})) {
                continue;
            }

            $this->buildVariantDocumentAndAssignToParentResult($response, $variantAccessKey, $resultDocument);
        }

        return $resultSet;
    }

    /**
     * Build the SearchResult of the variant and assigns it to the parent result document.
     *
     * @param \Apache_Solr_Response $response
     * @param string $variantAccessKey
     * @param SearchResult $resultDocument
     */
    protected function buildVariantDocumentAndAssignToParentResult(\Apache_Solr_Response $response, $variantAccessKey, SearchResult $resultDocument)
    {
        foreach ($response->{'expanded'}->{$variantAccessKey}->{'docs'} as $variantDocumentArray) {
            $variantDocument = new \Apache_Solr_Document();
            foreach (get_object_vars($variantDocumentArray) as $propertyName => $propertyValue) {
                $variantDocument->{$propertyName} = $propertyValue;
            }
            $variantSearchResult = $this->resultBuilder->fromApacheSolrDocument($variantDocument);
            $variantSearchResult->setIsVariant(true);
            $variantSearchResult->setVariantParent($resultDocument);

            $resultDocument->addVariant($variantSearchResult);
        }
    }

}