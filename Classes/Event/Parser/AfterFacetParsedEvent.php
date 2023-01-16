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

namespace ApacheSolrForTypo3\Solr\Event\Parser;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;

/**
 * This event is dispatched after an facet is parsed.
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
final class AfterFacetParsedEvent
{
    /**
     * The facet that was processed
     *
     * @var AbstractFacet
     */
    private AbstractFacet $facet;

    /**
     * The configuration of the facet
     *
     * @var array
     */
    private array $facetConfiguration;

    /**
     * @param AbstractFacet $facet
     * @param array $facetConfiguration
     */
    public function __construct(AbstractFacet $facet, array $facetConfiguration)
    {
        $this->facet = $facet;
        $this->facetConfiguration = $facetConfiguration;
    }

    /**
     * Returns the class name of the facet
     *
     * @return string
     */
    public function getFacetType(): string
    {
        return get_class($this->facet);
    }

    /**
     * @return AbstractFacet
     */
    public function getFacet(): AbstractFacet
    {
        return $this->facet;
    }

    /**
     * @return array
     */
    public function getFacetConfiguration(): array
    {
        return $this->facetConfiguration;
    }
}
