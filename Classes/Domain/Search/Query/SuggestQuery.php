<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\ReturnFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper\EscapeService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Util;

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
        parent::__construct();
        $keywords = (string)$keywords;

        $solrConfiguration = $solrConfiguration ?? Util::getSolrConfiguration();

        $this->setQuery($keywords);
        $this->configuration = $solrConfiguration->getObjectByPathOrDefault('plugin.tx_solr.suggest.', []);

        if (!empty($this->configuration['treatMultipleTermsAsSingleTerm'])) {
            $this->prefix = EscapeService::escape($keywords);
        } else {
            $matches = [];
            preg_match('/^(:?(.* |))([^ ]+)$/', $keywords, $matches);
            $fullKeywords = trim($matches[2]);
            $partialKeyword = trim($matches[3]);

            $this->setQuery($fullKeywords);
            $this->prefix = EscapeService::escape($partialKeyword);
        }

        $this->getEDisMax()->setQueryAlternative('*:*');
        $this->setFields(ReturnFields::fromString($this->configuration['suggestField'])->getValues());
        $this->addParam('facet', 'on');
        $this->addParam('facet.prefix', $this->prefix);
        $this->addParam('facet.field', $this->configuration['suggestField']);
        $this->addParam('facet.limit', $this->configuration['numberOfSuggestions']);
        $this->addParam('facet.mincount', 1);
        $this->addParam('facet.method', 'enum');
    }
}
