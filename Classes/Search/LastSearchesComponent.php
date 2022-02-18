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

use ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesWriterProcessor;

/**
 * Last searches search component
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class LastSearchesComponent extends AbstractComponent
{

    /**
     * Initializes the search component.
     *
     */
    public function initializeSearchComponent()
    {
        if ($this->searchConfiguration['lastSearches']) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch']['lastSearches'] = LastSearchesWriterProcessor::class;
        }
    }
}
