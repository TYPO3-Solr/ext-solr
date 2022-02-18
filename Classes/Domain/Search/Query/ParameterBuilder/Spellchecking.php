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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

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
