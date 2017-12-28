<?php
namespace ApacheSolrForTypo3\Solr\FieldProcessor;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Daniel Poetzinger <poetzinger@aoemedia.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\HierarchyTool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Processes a value that may appear as field value in documents
 *
 * @author Daniel Poetzinger <poetzinger@aoemedia.de>
 */
class PathToHierarchy implements FieldProcessor
{

    /**
     * Expects a value like "some/hierarchy/value"
     *
     * @param array $values Array of values, an array because of multivalued fields
     * @return array Modified array of values
     */
    public function process(array $values)
    {
        $results = [];

        foreach ($values as $value) {
            $valueResults = $this->buildSolrHierarchyFromPath($value);
            $results = array_merge($results, $valueResults);
        }

        return array_unique($results);
    }

    /**
     * Builds a Solr hierarchy from path string.
     *
     * @param string $path path string
     * @return array Solr hierarchy
     * @see http://wiki.apache.org/solr/HierarchicalFaceting
     */
    protected function buildSolrHierarchyFromPath($path)
    {
        $hierarchy = [];
        $path = HierarchyTool::substituteSlashes($path);

        $treeParts = GeneralUtility::trimExplode('/', $path, true);
        $currentTreeParts = [];

        foreach ($treeParts as $i => $part) {
            $currentTreeParts[] = $part;
            $hierarchyString = $i . '-' . implode('/', $currentTreeParts) . '/';
            $hierarchyString = HierarchyTool::unSubstituteSlashes($hierarchyString);
            $hierarchy[] = $hierarchyString;
        }

        return $hierarchy;
    }
}
