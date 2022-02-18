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

namespace ApacheSolrForTypo3\Solr\IndexQueue\Initializer;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;

/**
 * Interface to initialize items in the Index Queue.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface IndexQueueInitializer
{

    /**
     * Sets the site for the initializer.
     *
     * @param Site $site The site to initialize Index Queue items for.
     */
    public function setSite(Site $site);

    /**
     * Set the type (usually a Db table name) of items to initialize.
     *
     * @param string $type Type to initialize.
     */
    public function setType($type);

    /**
     * Sets the name of the indexing configuration to initialize.
     *
     * @param string $indexingConfigurationName Indexing configuration name
     */
    public function setIndexingConfigurationName($indexingConfigurationName);

    /**
     * Sets the configuration for how to index a type of items.
     *
     * @param array $indexingConfiguration Indexing configuration from TypoScript
     */
    public function setIndexingConfiguration(array $indexingConfiguration);

    /**
     * Initializes Index Queue items for a certain site and indexing
     * configuration.
     *
     * @return bool TRUE if initialization was successful, FALSE on error.
     */
    public function initialize();
}
