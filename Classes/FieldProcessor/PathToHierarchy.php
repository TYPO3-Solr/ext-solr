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

namespace ApacheSolrForTypo3\Solr\FieldProcessor;

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
    public function process(array $values): array
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
    protected function buildSolrHierarchyFromPath(string $path): array
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
