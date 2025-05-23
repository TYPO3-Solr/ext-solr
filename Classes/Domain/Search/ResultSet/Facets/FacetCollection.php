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

use ApacheSolrForTypo3\Solr\System\Data\AbstractCollection;

/**
 * Class FacetCollection
 */
class FacetCollection extends AbstractCollection
{
    public function addFacet(AbstractFacet $facet): void
    {
        $this->data[$facet->getName()] = $facet;
    }

    public function getUsed(): AbstractCollection
    {
        return $this->getFilteredCopy(
            function (AbstractFacet $facet) {
                return $facet->getIsUsed() && $facet->getIncludeInUsedFacets();
            },
        );
    }

    public function getAvailable(): AbstractCollection
    {
        return $this->getFilteredCopy(
            function (AbstractFacet $facet) {
                return $facet->getIsAvailable() && $facet->getIncludeInAvailableFacets() && $facet->getAllRequirementsMet();
            },
        );
    }

    public function getByGroupName(string $requiredGroup = 'all'): AbstractCollection
    {
        return $this->getFilteredCopy(
            function (AbstractFacet $facet) use ($requiredGroup) {
                return $facet->getGroupName() == $requiredGroup;
            },
        );
    }

    public function getByName(string $requiredName): AbstractCollection
    {
        return $this->getFilteredCopy(
            function (AbstractFacet $facet) use ($requiredName) {
                return $facet->getName() == $requiredName;
            },
        );
    }
}
