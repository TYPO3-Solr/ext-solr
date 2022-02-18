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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetPackage;

/**
 * Class DateRangePackage
 */
class DateRangePackage extends AbstractFacetPackage
{
    /**
     * @return string
     */
    public function getParserClassName() {
        return (string)DateRangeFacetParser::class;
    }

    /**
     * @return string
     */
    public function getQueryBuilderClassName()
    {
        return (string)DateRangeFacetQueryBuilder::class;
    }

    /**
     * @return string
     */
    public function getUrlDecoderClassName()
    {
        return (string)DateRangeUrlDecoder::class;
    }
}
