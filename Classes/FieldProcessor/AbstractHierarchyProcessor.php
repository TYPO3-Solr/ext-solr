<?php
namespace ApacheSolrForTypo3\Solr\FieldProcessor;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014-2015 Ingo Renner <ingo@typo3.org>
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
    protected function buildSolrHierarchyFromIdRootline(array $idRootline)
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
