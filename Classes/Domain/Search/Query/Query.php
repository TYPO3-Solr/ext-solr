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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query;

use Solarium\QueryType\Select\Query\Query as SolariumQuery;

class Query extends SolariumQuery {

    /**
     * Returns the query parameters that should be used.
     *
     * @return array
     */
    public function getQueryParameters() {
        return $this->getParams();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getQuery();
    }
}
