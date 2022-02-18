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

namespace ApacheSolrForTypo3\Solr\Search;

/**
 * FacetsModifier interface, allows to modify facet fields and their counts.
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 */
interface FacetsModifier
{

    /**
     * Modifies the given facets and returns the modified facets as array
     *
     * @param array $facets
     * @return array The facets with fields as array
     */
    public function modifyFacets($facets);
}
