<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange;

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
 * Class NumericRangePackage
 */
class NumericRangePackage extends AbstractFacetPackage {

    /**
     * @return string
     */
    public function getParserClassName() {
        return (string)NumericRangeFacetParser::class;
    }

    /**
     * @return string
     */
    public function getQueryBuilderClassName()
    {
        return (string)NumericRangeFacetQueryBuilder::class;
    }

    /**
     * @return string
     */
    public function getUrlDecoderClassName()
    {
        return (string)NumericRangeUrlDecoder::class;
    }
}
