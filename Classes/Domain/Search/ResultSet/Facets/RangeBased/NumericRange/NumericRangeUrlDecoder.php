<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
 *  (c) 2016 Markus Friedrich <markus.friedrich@dkd.de>
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
 * Parser to build Solr range queries from tx_solr[filter]
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 * @author Ingo Renner <ingo@typo3.org>
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class NumericRangeUrlDecoder implements FacetUrlDecoderInterface
{

    /**
     * Delimiter for ranges in the URL.
     *
     * @var string
     */
    const DELIMITER = '-';

    /**
     * Parses the given range from a GET parameter and returns a Solr range
     * filter.
     *
     * @param string $range The range filter from the URL.
     * @param array $configuration Facet configuration
     * @return string Lucene query language filter to be used for querying Solr
     * @throws \InvalidArgumentException
     */
    public function decode($range, array $configuration = [])
    {
        preg_match('/(-?\d*?)' . self::DELIMITER . '(-?\d*)/', $range, $filterParts);
        if ($filterParts[1] == '' || $filterParts[2] == '') {
            throw new \InvalidArgumentException(
                'Invalid numeric range given',
                1466062730
            );
        }

        return '[' . (int)$filterParts[1] . ' TO ' . (int)$filterParts[2] . ']';
    }
}
