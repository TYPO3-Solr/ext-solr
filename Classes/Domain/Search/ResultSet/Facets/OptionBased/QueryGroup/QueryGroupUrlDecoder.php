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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetUrlDecoderInterface;

/**
 * Filter encoder to build facet query parameters
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Ingo Renner <ingo@typo3.org>
 */
class QueryGroupUrlDecoder implements FacetUrlDecoderInterface
{
    /**
     * Parses the query filter from GET parameters in the URL and translates it
     * to a Lucene filter value.
     *
     * @param string $value the filter query from plugin
     * @param array $configuration options set in a facet's configuration
     * @return string Value to be used in a Lucene filter
     */
    public function decode(string $value, array $configuration = []): string
    {
        return $configuration[$value . '.']['query'];
    }
}
