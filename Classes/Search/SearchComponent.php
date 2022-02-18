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

/**
 * Search component interface.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface SearchComponent
{

    /**
     * Provides the plugin's search configuration from plugin.tx_solr.search
     *
     * @param array $configuration Configuration
     */
    public function setSearchConfiguration(array $configuration);

    /**
     * Initializes the search component.
     *
     */
    public function initializeSearchComponent();
}
