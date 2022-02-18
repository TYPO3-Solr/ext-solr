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
 * Abstract search component
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
abstract class AbstractComponent implements SearchComponent
{

    /**
     * Search configuration - plugin.tx_solr.search
     *
     * @var array
     */
    protected $searchConfiguration = [];

    /**
     * Sets the plugin's search configuration.
     *
     * @param array $configuration Configuration
     */
    public function setSearchConfiguration(array $configuration)
    {
        $this->searchConfiguration = $configuration;
    }
}
