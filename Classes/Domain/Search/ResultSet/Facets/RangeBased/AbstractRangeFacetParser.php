<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\System\Solr\ParsingUtil;

/**
 * Class AbstractRangeFacetParser
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractRangeFacetParser extends AbstractFacetParser
{
    /**
     * @param SearchResultSet $resultSet
     * @param string $facetName
     * @param array $facetConfiguration
     * @param string $facetClass
     * @param string $facetItemClass
     * @param string $facetRangeCountClass
     * @return AbstractRangeFacet|null
     */
    protected function getParsedFacet(SearchResultSet $resultSet, $facetName, array $facetConfiguration, $facetClass, $facetItemClass, $facetRangeCountClass)
    {
        $fieldName = $facetConfiguration['field'];
        $label = $this->getPlainLabelOrApplyCObject($facetConfiguration);
        $activeValue = $this->getActiveFacetValuesFromRequest($resultSet, $facetName);
        $response = $resultSet->getResponse();

        $valuesFromResponse = isset($response->facet_counts->facet_ranges->{$fieldName}) ? get_object_vars($response->facet_counts->facet_ranges->{$fieldName}) : [];

        $facet = $this->objectManager->get(
            $facetClass,
            $resultSet,
            $facetName,
            $fieldName,
            $label,
            $facetConfiguration
        );

        $facet->setIsAvailable(count($valuesFromResponse) > 0);
        $facet->setIsUsed(count($activeValue) > 0);

        if (is_array($valuesFromResponse)) {
            $rangeCounts = [];
            $allCount = 0;

            $countsFromResponse = isset($valuesFromResponse['counts']) ? ParsingUtil::getMapArrayFromFlatArray($valuesFromResponse['counts']) : [];

            foreach ($countsFromResponse as $rangeCountValue => $count) {
                $rangeCountValue = $this->parseResponseValue($rangeCountValue);
                $rangeCount = $this->objectManager->get($facetRangeCountClass, $rangeCountValue, $count);
                $rangeCounts[] = $rangeCount;
                $allCount += $count;
            }

            $fromInResponse = $this->parseResponseValue($valuesFromResponse['start']);
            $toInResponse = $this->parseResponseValue($valuesFromResponse['end']);

            if (preg_match('/(-?\d*?)-(-?\d*)/', $activeValue[0], $rawValues) == 1) {
                $rawFrom = $rawValues[1];
                $rawTo = $rawValues[2];
            } else {
                $rawFrom = 0;
                $rawTo = 0;
            }

            $from = $this->parseRequestValue($rawFrom);
            $to = $this->parseRequestValue($rawTo);

            $type = isset($facetConfiguration['type']) ? $facetConfiguration['type'] : 'numericRange';
            $gap = isset($facetConfiguration[$type . '.']['gap']) ? $facetConfiguration[$type . '.']['gap'] : 1;

            $range = $this->objectManager->get($facetItemClass, $facet, $from, $to, $fromInResponse, $toInResponse, $gap, $allCount, $rangeCounts, true);
            $facet->setRange($range);
        }

        return $facet;
    }

    /**
     * @param string $requestValue
     * @return mixed
     */
    abstract protected function parseRequestValue($requestValue);

    /**
     * @param string $responseValue
     * @return mixed
     */
    abstract protected function parseResponseValue($responseValue);
}
