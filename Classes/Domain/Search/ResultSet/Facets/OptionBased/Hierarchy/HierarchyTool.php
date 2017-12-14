<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy;

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

class HierarchyTool
{
    /**
     * Replaces all escaped slashes in a hierarchy path with @@@slash@@@ to afterwards
     * only have slashes in the content that are real path separators.
     *
     * @param string $pathWithContentSlashes
     * @return string
     */
    public static function substituteSlashes(string $pathWithContentSlashes): string
    {
        return  (string)str_replace('\/', '@@@slash@@@', $pathWithContentSlashes);
    }

    /**
     * Replaces @@@slash@@@ with \/ to have the path usable for solr again.
     *
     * @param string $pathWithReplacedContentSlashes
     * @return string
     */
    public static function unSubstituteSlashes(string $pathWithReplacedContentSlashes): string
    {
        return (string)str_replace('@@@slash@@@', '\/', $pathWithReplacedContentSlashes);
    }
}