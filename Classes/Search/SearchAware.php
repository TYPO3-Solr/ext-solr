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

namespace ApacheSolrForTypo3\Solr\Search;

use ApacheSolrForTypo3\Solr\Search;

/**
 * Search awareness interface for extension components.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface SearchAware
{

    /**
     * Provides the extension component with an instance of the currently
     * active search.
     *
     * @param Search $search Currently active search instance
     */
    public function setSearch(Search $search);
}
