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

namespace ApacheSolrForTypo3\Solr\FieldProcessor;

/**
 * Provides common methods for field processors creating a hierarchy.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
abstract class AbstractHierarchyProcessor
{

    /**
     * Builds a Solr hierarchy from an array of uids that make up a rootline.
     *
     * @param array $idRootline Array of Ids representing a rootline
     * @return array Solr hierarchy
     * @see http://wiki.apache.org/solr/HierarchicalFaceting
     */
    protected function buildSolrHierarchyFromIdRootline(array $idRootline): array
    {
        $hierarchy = [];

        $depth = 0;
        $currentPath = array_shift($idRootline);
        if (is_null($currentPath)) {
            return $hierarchy;
        }

        foreach ($idRootline as $uid) {
            $hierarchy[] = $depth . '-' . $currentPath . '/';

            $depth++;
            $currentPath .= '/' . $uid;
        }
        $hierarchy[] = $depth . '-' . $currentPath . '/';

        return $hierarchy;
    }
}
