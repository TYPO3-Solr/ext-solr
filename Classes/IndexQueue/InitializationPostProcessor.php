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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;

/**
 * Interface to post process initialization of the Index Queue.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface InitializationPostProcessor
{

    /**
     * Post process Index Queue initialization
     *
     * @param Site $site The site to initialize
     * @param array $indexingConfigurations Initialized indexing configurations
     * @param array $initializationStatus Results of Index Queue initializations
     */
    public function postProcessIndexQueueInitialization(
        Site $site,
        array $indexingConfigurations,
        array $initializationStatus
    );
}
