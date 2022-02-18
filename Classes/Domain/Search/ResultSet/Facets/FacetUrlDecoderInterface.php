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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets;

/**
 * The facet url encode is responsible to encode and decode values for EXT:solr urls.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Timo Hund <timo.hund@dkd.de>
 */
interface FacetUrlDecoderInterface
{

    /**
     * Parses the query filter from GET parameters in the URL and translates it
     * to a Lucene filter value.
     *
     * @param string $value the filter query from plugin
     * @param array $configuration Facet configuration
     * @return string Value to be used in a Lucene filter
     */
    public function decode($value, array $configuration = []);
}
