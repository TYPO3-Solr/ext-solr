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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets;

use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service class to check for a facet if allRequirements are met for that facet.
 */
class RequirementsService
{
    /**
     * Checks if facet meets all requirements.
     *
     * Evaluates configuration in "plugin.tx_solr.search.faceting.facets.[facetName].requirements",
     */
    public function getAllRequirementsMet(AbstractFacet $facet): bool
    {
        $requirements = $facet->getRequirements();
        if (count($requirements) === 0) {
            return true;
        }

        foreach ($requirements as $requirement) {
            $requirementMet = $this->getRequirementMet($facet, $requirement);
            $requirementMet = $this->getNegationWhenConfigured($requirementMet, $requirement);

            if (!$requirementMet) {
                // early return as soon as one requirement is not met
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a single requirement is met.
     */
    protected function getRequirementMet(
        AbstractFacet $facet,
        array $requirement = [],
    ): bool {
        $selectedItemValues = $this->getSelectedItemValues($facet, $requirement['facet']);
        $csvActiveFacetItemValues = implode(', ', $selectedItemValues);
        $requirementValues = GeneralUtility::trimExplode(',', $requirement['values']);

        foreach ($requirementValues as $value) {
            $noFacetOptionSelectedRequirementMet = ($value === '__none' && empty($selectedItemValues));
            $anyFacetOptionSelectedRequirementMet = ($value === '__any' && !empty($selectedItemValues));

            if ($noFacetOptionSelectedRequirementMet || $anyFacetOptionSelectedRequirementMet || in_array($value, $selectedItemValues) || fnmatch($value, $csvActiveFacetItemValues)) {
                // when we find a single matching requirement we can exit and return true
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the active item values of a facet
     *
     * @return string[]|int[]
     */
    protected function getSelectedItemValues(AbstractFacet $facet, string $facetNameToCheckRequirementsOn): array
    {
        $facetToCheckRequirements = $facet->getResultSet()->getFacets()->getByName($facetNameToCheckRequirementsOn)->getByPosition(0);
        if (!$facetToCheckRequirements instanceof AbstractFacet) {
            throw new InvalidArgumentException(
                'Requirement for non-existing facet configured',
                4953268822,
            );
        }

        if (!$facetToCheckRequirements->getIsUsed()) {
            // unused facets do not have active values.
            return [];
        }

        $itemValues = [];
        $activeFacetItems = $facetToCheckRequirements->getAllFacetItems();
        foreach ($activeFacetItems as $item) {
            /** @var AbstractFacetItem $item */
            if ($item->getSelected()) {
                $itemValues[] = $item->getUriValue();
            }
        }

        return $itemValues;
    }

    /**
     * Negates the result when configured.
     */
    protected function getNegationWhenConfigured(bool $value, ?array $configuration = null): bool
    {
        if (!is_array($configuration) || empty($configuration['negate'])) {
            return $value;
        }

        return !($value);
    }
}
