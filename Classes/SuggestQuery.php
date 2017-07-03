<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * A query specialized to get search suggestions
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class SuggestQuery extends Query
{
    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * SuggestQuery constructor.
     *
     * @param string $keywords
     * @param TypoScriptConfiguration $solrConfiguration
     */
    public function __construct($keywords, $solrConfiguration = null)
    {
        $keywords = (string)$keywords;
        if ($solrConfiguration == null) {
            $solrConfiguration = Util::getSolrConfiguration();
        }

        parent::__construct('', $solrConfiguration);

        $this->configuration = $solrConfiguration->getObjectByPathOrDefault('plugin.tx_solr.suggest.', []);

        if (!empty($this->configuration['treatMultipleTermsAsSingleTerm'])) {
            $this->prefix = $this->escapeService->escape($keywords);
        } else {
            $matches = [];
            preg_match('/^(:?(.* |))([^ ]+)$/', $keywords, $matches);
            $fullKeywords = trim($matches[2]);
            $partialKeyword = trim($matches[3]);

            $this->setKeywords($fullKeywords);
            $this->prefix = $this->escapeService->escape($partialKeyword);
        }

        $this->setAlternativeQuery('*:*');
    }

    /**
     * @return void
     */
    protected function initializeQuery()
    {
        $this->initializeFilters();
    }

    /**
     * Returns the query parameters that should be used.
     *
     * @return array
     */
    public function getQueryParameters()
    {
        $suggestParameters = [
            'facet' => 'on',
            'facet.prefix' => $this->prefix,
            'facet.field' => $this->configuration['suggestField'],
            'facet.limit' => $this->configuration['numberOfSuggestions'],
            'facet.mincount' => '1',
            'fq' => $this->filters,
            'fl' => $this->configuration['suggestField']
        ];

        return array_merge($suggestParameters, $this->queryParameters);
    }
}
