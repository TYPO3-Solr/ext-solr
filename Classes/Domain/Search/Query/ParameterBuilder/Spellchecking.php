<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\AbstractQueryBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * The Spellchecking ParameterProvider is responsible to build the solr query parameters
 * that are needed for the spellchecking.
 */
class Spellchecking extends AbstractDeactivatable implements ParameterBuilder
{

    /**
     * @var int
     */
    protected $maxCollationTries = 0;

    /**
     * Spellchecking constructor.
     *
     * @param bool $isEnabled
     * @param int $maxCollationTries
     */
    public function __construct($isEnabled = false, int $maxCollationTries = 0)
    {
        $this->isEnabled = $isEnabled;
        $this->maxCollationTries = $maxCollationTries;
    }

    /**
     * @return int
     */
    public function getMaxCollationTries(): int
    {
        return $this->maxCollationTries;
    }

    /**
     * @param TypoScriptConfiguration $solrConfiguration
     * @return Spellchecking
     */
    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration)
    {
        $isEnabled = $solrConfiguration->getSearchSpellchecking();
        if (!$isEnabled) {
            return new Spellchecking(false);
        }

        $maxCollationTries = $solrConfiguration->getSearchSpellcheckingNumberOfSuggestionsToTry();

        return new Spellchecking($isEnabled, $maxCollationTries);
    }

    /**
     * @return Spellchecking
     */
    public static function getEmpty()
    {
        return new Spellchecking(false);
    }

    /**
     * @param AbstractQueryBuilder $parentBuilder
     * @return AbstractQueryBuilder
     */
    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $query = $parentBuilder->getQuery();
        if (!$this->getIsEnabled()) {
            $query->removeComponent($query->getSpellcheck());
            return $parentBuilder;
        }

        $query->getSpellcheck()->setMaxCollationTries($this->getMaxCollationTries());
        $query->getSpellcheck()->setCollate(true);
        return $parentBuilder;
    }
}
