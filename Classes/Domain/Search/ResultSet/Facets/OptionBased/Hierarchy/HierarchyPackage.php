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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetPackage;

/**
 * Class HierarchyPackage
 */
class HierarchyPackage extends AbstractFacetPackage {

    /**
     * @return string
     */
    public function getParserClassName() {
        return (string)HierarchyFacetParser::class;
    }

    /**
     * @return string
     */
    public function getUrlDecoderClassName() {
        return (string)HierarchyUrlDecoder::class;
    }
}
