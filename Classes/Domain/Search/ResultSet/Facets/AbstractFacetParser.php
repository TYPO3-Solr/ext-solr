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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\AbstractOptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Class AbstractFacetParser
 */
abstract class AbstractFacetParser implements FacetParserInterface
{
    protected static ?ContentObjectRenderer $reUseAbleContentObject = null;

    /**
     * Returns the reusable {@link ContentObjectRenderer}
     */
    protected function getReusableContentObject(): ContentObjectRenderer
    {
        if (self::$reUseAbleContentObject !== null) {
            return self::$reUseAbleContentObject;
        }

        self::$reUseAbleContentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        return self::$reUseAbleContentObject;
    }

    /**
     * Returns plain label or applies the cObject
     */
    protected function getPlainLabelOrApplyCObject(array $configuration): string
    {
        // when no label is configured we return an empty string
        if (!isset($configuration['label'])) {
            return '';
        }

        // when no sub configuration is set, we use the string, configured as label
        if (!isset($configuration['label.'])) {
            return $configuration['label'];
        }

        // when label and label. was set, we apply the cObject
        return $this->getReusableContentObject()->cObjGetSingle($configuration['label'], $configuration['label.']);
    }

    /**
     * Returns label from rendering instructions
     */
    protected function getLabelFromRenderingInstructions(
        string|int $value,
        int $count,
        string $facetName,
        array $facetConfiguration,
    ): string {
        $hasRenderingInstructions = isset($facetConfiguration['renderingInstruction']) && isset($facetConfiguration['renderingInstruction.']);
        if (!$hasRenderingInstructions) {
            return (string)$value;
        }

        $this->getReusableContentObject()->start(['optionValue' => $value, 'optionCount' => $count, 'facetName' => $facetName]);
        return $this->getReusableContentObject()->cObjGetSingle(
            $facetConfiguration['renderingInstruction'],
            $facetConfiguration['renderingInstruction.'],
        );
    }

    /**
     * Retrieves the active facetValue for a facet from the search request.
     */
    protected function getActiveFacetValuesFromRequest(SearchResultSet $resultSet, string $facetName): array
    {
        return $resultSet->getUsedSearchRequest()->getActiveFacetValuesByName($facetName);
    }

    /**
     * Returns merged facet value from search request and Apache Solr response
     */
    protected function getMergedFacetValueFromSearchRequestAndSolrResponse(
        array $facetValuesFromSolrResponse,
        array $facetValuesFromSearchRequest,
    ): array {
        $facetValueItemsToCreate = $facetValuesFromSolrResponse;

        foreach ($facetValuesFromSearchRequest as $valueFromRequest) {
            // if we have options in the request that have not been in the response we add them with a count of 0
            if (!isset($facetValueItemsToCreate[$valueFromRequest])) {
                $facetValueItemsToCreate[$valueFromRequest] = 0;
            }
        }
        return $facetValueItemsToCreate;
    }

    /**
     * Applies manual sort order from facet configuration.
     */
    protected function applyManualSortOrder(
        AbstractOptionsFacet $facet,
        array $facetConfiguration,
    ): AbstractOptionsFacet {
        if (!isset($facetConfiguration['manualSortOrder'])) {
            return $facet;
        }
        $delimiter = trim($facetConfiguration['manualSortOrderDelimiter'] ?? ',');
        $fields = GeneralUtility::trimExplode($delimiter, $facetConfiguration['manualSortOrder']);
        // @extensionScannerIgnoreLine
        $sortedOptions = $facet->getOptions()->getManualSortedCopy($fields);

        // @extensionScannerIgnoreLine
        $facet->setOptions($sortedOptions);

        return $facet;
    }

    /**
     * Applies reverse order on facet
     */
    protected function applyReverseOrder(AbstractOptionsFacet $facet, array $facetConfiguration): AbstractOptionsFacet
    {
        if (empty($facetConfiguration['reverseOrder'])) {
            return $facet;
        }

        // @extensionScannerIgnoreLine
        $facet->setOptions($facet->getOptions()->getReversedOrderCopy());

        return $facet;
    }

    /**
     * Checks whether the facet value is excluded
     */
    protected function getIsExcludedFacetValue(string|int $value, array $facetConfiguration): bool
    {
        if (!isset($facetConfiguration['excludeValues'])) {
            return false;
        }

        $excludedValue = GeneralUtility::trimExplode(',', $facetConfiguration['excludeValues']);
        return in_array((string)$value, $excludedValue);
    }
}
