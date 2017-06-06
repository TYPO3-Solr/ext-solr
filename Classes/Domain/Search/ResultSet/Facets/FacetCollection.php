<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets;

use ApacheSolrForTypo3\Solr\System\Data\AbstractCollection;

/**
 * Class FacetCollection
 * @package ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets
 */
class FacetCollection extends AbstractCollection
{

    /**
     * @param AbstractFacet $facet
     */
    public function addFacet(AbstractFacet $facet)
    {
        $this->data[$facet->getName()] = $facet;
    }

    /**
     * @return FacetCollection
     */
    public function getUsed()
    {
        return $this->getFilteredCopy(
            function(AbstractFacet $facet) {
                return $facet->getIsUsed();
            }
        );
    }

    /**
     * @return FacetCollection
     */
    public function getAvailable()
    {
        return $this->getFilteredCopy(
            function(AbstractFacet $facet) {
                return $facet->getIsAvailable();
            }
        );
    }

    /**
     * @param string $requiredGroup
     * @return AbstractCollection
     */
    public function getByGroupName($requiredGroup = 'all')
    {
        return $this->getFilteredCopy(
            function(AbstractFacet $facet) use ($requiredGroup) {
                return $facet->getGroupName() == $requiredGroup;
            }
        );
    }

    /**
     * @param int $position
     * @return AbstractFacet
     */
    public function getByPosition($position)
    {
        return parent::getByPosition($position);
    }
}
