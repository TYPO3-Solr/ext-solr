<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\AbstractOptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Class AbstractFacetParser
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractFacetParser implements FacetParserInterface
{
    /**
     * @var ContentObjectRenderer
     */
    protected static $reUseAbleContentObject;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @return ContentObjectRenderer
     */
    protected function getReUseAbleContentObject()
    {
        /** @var $contentObject ContentObjectRenderer */
        if (self::$reUseAbleContentObject !== null) {
            return self::$reUseAbleContentObject;
        }

        self::$reUseAbleContentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        return self::$reUseAbleContentObject;
    }

    /**
     * @param array $configuration
     * @return string
     */
    protected function getPlainLabelOrApplyCObject($configuration)
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
        return $this->getReUseAbleContentObject()->cObjGetSingle($configuration['label'], $configuration['label.']);
    }

    /**
     * @param mixed $value
     * @param integer $count
     * @param string $facetName
     * @param array $facetConfiguration
     * @return string
     */
    protected function getLabelFromRenderingInstructions($value, $count, $facetName, $facetConfiguration)
    {
        $hasRenderingInstructions = isset($facetConfiguration['renderingInstruction']) && isset($facetConfiguration['renderingInstruction.']);
        if (!$hasRenderingInstructions) {
            return $value;
        }

        $this->getReUseAbleContentObject()->start(['optionValue' => $value, 'optionCount' => $count, 'facetName' => $facetName]);
        return $this->getReUseAbleContentObject()->cObjGetSingle(
            $facetConfiguration['renderingInstruction'],
            $facetConfiguration['renderingInstruction.']
        );
    }


    /**
     * Retrieves the active facetValue for a facet from the search request.
     * @param SearchResultSet $resultSet
     * @param string $facetName
     * @return array
     */
    protected function getActiveFacetValuesFromRequest(SearchResultSet $resultSet, $facetName)
    {
        $activeFacetValues = $resultSet->getUsedSearchRequest()->getActiveFacetValuesByName($facetName);
        $activeFacetValues = is_array($activeFacetValues) ? $activeFacetValues : [];

        return $activeFacetValues;
    }


    /**
     * @param array $facetValuesFromSolrResponse
     * @param array $facetValuesFromSearchRequest
     * @return mixed
     */
    protected function getMergedFacetValueFromSearchRequestAndSolrResponse($facetValuesFromSolrResponse, $facetValuesFromSearchRequest)
    {
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
     * @param AbstractOptionsFacet $facet
     * @param array $facetConfiguration
     * @return AbstractOptionsFacet
     */
    protected function applyManualSortOrder(AbstractOptionsFacet $facet, array $facetConfiguration)
    {
        if (!isset($facetConfiguration['manualSortOrder'])) {
            return $facet;
        }
        $fields = GeneralUtility::trimExplode(',', $facetConfiguration['manualSortOrder']);
        // @extensionScannerIgnoreLine
        $sortedOptions = $facet->getOptions()->getManualSortedCopy($fields);

        // @extensionScannerIgnoreLine
        $facet->setOptions($sortedOptions);

        return $facet;
    }

    /**
     * @param AbstractOptionsFacet $facet
     * @param array $facetConfiguration
     * @return AbstractOptionsFacet
     */
    protected function applyReverseOrder(AbstractOptionsFacet $facet, array $facetConfiguration)
    {
        if (empty($facetConfiguration['reverseOrder'])) {
            return $facet;
        }

        // @extensionScannerIgnoreLine
        $facet->setOptions($facet->getOptions()->getReversedOrderCopy());

        return $facet;
    }

    /**
     * @param mixed $value
     * @param array $facetConfiguration
     * @return boolean
     */
    protected function getIsExcludedFacetValue($value, array $facetConfiguration)
    {
        if (!isset($facetConfiguration['excludeValues'])) {
            return false;
        }

        $excludedValue = GeneralUtility::trimExplode(',', $facetConfiguration['excludeValues']);
        return in_array($value, $excludedValue);
    }
}
