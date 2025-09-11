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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\InvalidFacetPackageException;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RequirementsService;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting\Sorting;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Spellchecking\Suggestion;
use stdClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use UnexpectedValueException;

/**
 * This processor is used to transform the solr response into a
 * domain object hierarchy that can be used in the application (controller and view).
 */
class ResultSetReconstitutionProcessor implements SearchResultSetProcessor
{
    protected function getFacetRegistry(): FacetRegistry
    {
        // @extensionScannerIgnoreLine
        return GeneralUtility::makeInstance(FacetRegistry::class);
    }

    /**
     * The implementation can be used to influence a SearchResultSet that is
     * created and processed in the SearchResultSetService.
     *
     * @throws InvalidFacetPackageException
     */
    public function process(SearchResultSet $resultSet): SearchResultSet
    {
        $resultSet = $this->parseSpellCheckingResponseIntoObjects($resultSet);
        $resultSet = $this->parseSortingIntoObjects($resultSet);

        // here we can reconstitute other domain objects from the solr response
        return $this->parseFacetsIntoObjects($resultSet);
    }

    /**
     * Parses/converts sortings from raw response into desired object structure.
     */
    protected function parseSortingIntoObjects(SearchResultSet $resultSet): SearchResultSet
    {
        $configuration = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration();
        $activeSortings = $resultSet->getUsedSearchRequest()->getSeperatedSortings();
        $hasSorting = $resultSet->getUsedSearchRequest()->getHasSorting();

        // no configuration available
        if (!isset($configuration)) {
            return $resultSet;
        }

        // no sorting enabled
        if (!$configuration->getSearchSorting()) {
            return $resultSet;
        }
        foreach ($configuration->getSearchSortingOptionsConfiguration() as $sortingKeyName => $sortingOptions) {
            $sortingName = rtrim($sortingKeyName, '.');
            $selected = false;
            $direction = $configuration->getSearchSortingDefaultOrderBySortOptionName($sortingName);

            // when we have an active sorting in the request we compare the sortingName and mark is as active and
            // use the direction from the request
            if ($hasSorting && array_key_exists($sortingName, $activeSortings)) {
                $selected = true;
                $direction = $activeSortings[$sortingName];
            }

            $field = $sortingOptions['field'];
            $label = $sortingOptions['label'] ?? '';

            $isResetOption = $field === 'relevance' || $field === '$q_vector';

            // Allow stdWrap on label:
            $labelHasSubConfiguration = is_array($sortingOptions['label.'] ?? null);
            if ($labelHasSubConfiguration) {
                $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
                $label = $cObj->stdWrap($label, $sortingOptions['label.']);
            }

            if ($isResetOption && !$hasSorting) {
                $selected = true;
            }

            $sorting = GeneralUtility::makeInstance(
                Sorting::class,
                $resultSet,
                $sortingName,
                $field,
                $direction,
                $label,
                $selected,
                $isResetOption
            );
            $resultSet->addSorting($sorting);
        }

        return $resultSet;
    }

    /**
     * Parses/converts spell-checking from raw response into desired object structure.
     */
    private function parseSpellCheckingResponseIntoObjects(SearchResultSet $resultSet): SearchResultSet
    {
        //read the response
        $response = $resultSet->getResponse();

        if (!is_array($response->spellcheck->suggestions ?? null)) {
            return $resultSet;
        }

        $misspelledTerm = '';
        foreach ($response->spellcheck->suggestions as $key => $suggestionData) {
            if (is_string($suggestionData)) {
                $misspelledTerm = $key;
                continue;
            }

            if ($misspelledTerm === '') {
                throw new UnexpectedValueException('No misspelled term before suggestion');
            }

            if (!is_object($suggestionData) && !is_array($suggestionData->suggestion)) {
                continue;
            }

            foreach ($suggestionData->suggestion as $suggestedTerm) {
                $suggestion = $this->createSuggestionFromResponseFragment($suggestionData, $suggestedTerm, $misspelledTerm);
                //add it to the resultSet
                $resultSet->addSpellCheckingSuggestion($suggestion);
            }
        }

        return $resultSet;
    }

    /**
     * Creates and returns the suggestion from response fragment
     */
    private function createSuggestionFromResponseFragment(
        stdClass $suggestionData,
        string $suggestedTerm,
        string $misspelledTerm,
    ): Suggestion {
        $numFound = $suggestionData->numFound ?? 0;
        $startOffset = $suggestionData->startOffset ?? 0;
        $endOffset = $suggestionData->endOffset ?? 0;

        // by now we avoid to use GeneralUtility::makeInstance, since we only create a value object
        // and the usage might be an overhead.
        return new Suggestion($suggestedTerm, $misspelledTerm, $numFound, $startOffset, $endOffset);
    }

    /**
     * Parse available facets into objects
     *
     * @throws InvalidFacetPackageException
     */
    private function parseFacetsIntoObjects(SearchResultSet $resultSet): SearchResultSet
    {
        // Make sure we can access the facet configuration
        if (!$resultSet->getUsedSearchRequest() || !$resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()) {
            return $resultSet;
        }

        // Read the response
        $response = $resultSet->getResponse();
        if (!is_object($response->facet_counts) && !is_object($response->facets)) {
            return $resultSet;
        }

        $facetRegistry = $this->getFacetRegistry();
        $facetsConfiguration = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchFacetingFacets();

        foreach ($facetsConfiguration as $name => $options) {
            if (!is_array($options)) {
                continue;
            }
            $facetName = rtrim($name, '.');
            $type = !empty($options['type']) ? $options['type'] : '';

            $parser = $facetRegistry->getPackage($type)->getParser();
            $facet = $parser->parse($resultSet, $facetName, $options);
            if ($facet !== null) {
                $resultSet->addFacet($facet);
            }
        }

        $this->applyRequirements($resultSet);

        return $resultSet;
    }

    /**
     * Applies the requirements to the result set
     */
    protected function applyRequirements(SearchResultSet $resultSet): void
    {
        $requirementsService = $this->getRequirementsService();
        $facets = $resultSet->getFacets();
        foreach ($facets as $facet) {
            /** @var AbstractFacet $facet */
            $requirementsMet = $requirementsService->getAllRequirementsMet($facet);
            $facet->setAllRequirementsMet($requirementsMet);
        }
    }

    protected function getRequirementsService(): RequirementsService
    {
        // @extensionScannerIgnoreLine
        return GeneralUtility::makeInstance(RequirementsService::class);
    }
}
