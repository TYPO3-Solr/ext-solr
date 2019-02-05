<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue\Initializer;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

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
