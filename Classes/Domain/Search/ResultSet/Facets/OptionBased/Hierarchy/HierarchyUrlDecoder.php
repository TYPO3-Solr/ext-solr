<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetUrlDecoderInterface;

/**
 * Filter encoder to build Solr hierarchy queries from tx_solr[filter]
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class HierarchyUrlDecoder implements FacetUrlDecoderInterface
{

    /**
     * Delimiter for hierarchies in the URL.
     *
     * @var string
     */
    const DELIMITER = '/';

    /**
     * Parses the given hierarchy filter and returns a Solr filter query.
     *
     * @param string $hierarchy The hierarchy filter query.
     * @param array $configuration Facet configuration
     * @return string Lucene query language filter to be used for querying Solr
     */
    public function decode($hierarchy, array $configuration = [])
    {
        $hierarchy = substr($hierarchy, 1);
        $hierarchy = rtrim($hierarchy, '/');
        $hierarchyItems = explode(self::DELIMITER, $hierarchy);

        $hierarchyFilter = '"' . (count($hierarchyItems) - 1) . '-' . $hierarchy . '/"';

        return $hierarchyFilter;
    }
}
