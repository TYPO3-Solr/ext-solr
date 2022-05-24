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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy;

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
     * @param string $value The hierarchy filter query.
     * @param array $configuration Facet configuration
     * @return string Lucene query language filter to be used for querying Solr
     */
    public function decode(string $value, array $configuration = []): string
    {
        $escapedHierarchy = HierarchyTool::substituteSlashes($value);

        $escapedHierarchy = substr($escapedHierarchy, 1);
        $escapedHierarchy = rtrim($escapedHierarchy, '/');
        $hierarchyItems = explode(self::DELIMITER, $escapedHierarchy);
        $filterContent = (count($hierarchyItems) - 1) . '-' . $escapedHierarchy . '/';

        $filterContent = HierarchyTool::unSubstituteSlashes($filterContent);

        return '"' . str_replace('\\', '\\\\', $filterContent) . '"';
    }
}
