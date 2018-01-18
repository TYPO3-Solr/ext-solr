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
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * The Slops ParameterProvider is responsible to build the solr query parameters
 * that are needed for the several slop arguments.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder
 */
class Slops implements ParameterBuilder
{
    const NO_SLOP = null;

    /**
     * The qs parameter
     *
     * @var int
     */
    protected $querySlop = self::NO_SLOP;

    /**
     * @var int
     */
    protected $phraseSlop = self::NO_SLOP;

    /**
     * @var int
     */
    protected $bigramPhraseSlop = self::NO_SLOP;

    /**
     * @var int
     */
    protected $trigramPhraseSlop = self::NO_SLOP;

    /**
     * Slops constructor.
     * @param int|null $querySlop
     * @param int|null $phraseSlop
     * @param int|null $bigramPhraseSlop
     * @param int|null $trigramPhraseSlop
     */
    public function __construct($querySlop = self::NO_SLOP, $phraseSlop = self::NO_SLOP, $bigramPhraseSlop = self::NO_SLOP, $trigramPhraseSlop = self::NO_SLOP)
    {
        $this->querySlop = $querySlop;
        $this->phraseSlop = $phraseSlop;
        $this->bigramPhraseSlop = $bigramPhraseSlop;
        $this->trigramPhraseSlop = $trigramPhraseSlop;
    }

    /**
     * @return int
     */
    public function getQuerySlop(): int
    {
        return $this->querySlop;
    }

    /**
     * @param int $querySlop
     */
    public function setQuerySlop(int $querySlop)
    {
        $this->querySlop = $querySlop;
    }

    /**
     * @return int
     */
    public function getPhraseSlop(): int
    {
        return $this->phraseSlop;
    }

    /**
     * @param int $phraseSlop
     */
    public function setPhraseSlop(int $phraseSlop)
    {
        $this->phraseSlop = $phraseSlop;
    }

    /**
     * @return int
     */
    public function getBigramPhraseSlop(): int
    {
        return $this->bigramPhraseSlop;
    }

    /**
     * @param int $bigramPhraseSlop
     */
    public function setBigramPhraseSlop(int $bigramPhraseSlop)
    {
        $this->bigramPhraseSlop = $bigramPhraseSlop;
    }

    /**
     * @return int
     */
    public function getTrigramPhraseSlop(): int
    {
        return $this->trigramPhraseSlop;
    }

    /**
     * @param int $trigramPhraseSlop
     */
    public function setTrigramPhraseSlop(int $trigramPhraseSlop)
    {
        $this->trigramPhraseSlop = $trigramPhraseSlop;
    }

    /**
     * @param Query $query
     * @return Query
     */
    public function build(Query $query): Query
    {
        $query->getQueryParametersContainer()->setWhenIntOrUnsetWhenNull('qs', $this->querySlop);
        $query->getQueryParametersContainer()->setWhenIntOrUnsetWhenNull('ps', $this->phraseSlop);
        $query->getQueryParametersContainer()->setWhenIntOrUnsetWhenNull('ps2', $this->bigramPhraseSlop);
        $query->getQueryParametersContainer()->setWhenIntOrUnsetWhenNull('ps3', $this->trigramPhraseSlop);

        return $query;
    }

    /**
     * @param TypoScriptConfiguration $solrConfiguration
     * @return Slops
     */
    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration)
    {
        $searchConfiguration = $solrConfiguration->getSearchConfiguration();
        $querySlop = static::getQuerySlopFromConfiguration($searchConfiguration);
        $phraseSlop = static::getPhraseSlopFromConfiguration($searchConfiguration);
        $bigramPhraseSlop = static::getBigramPhraseSlopFromConfiguration($searchConfiguration);
        $trigramPhraseSlop = static::getTrigramPhraseSlopFromConfiguration($searchConfiguration);
        return new Slops($querySlop, $phraseSlop, $bigramPhraseSlop, $trigramPhraseSlop);
    }

    /**
     * @param array $searchConfiguration
     * @return int|null
     */
    protected static function getPhraseSlopFromConfiguration($searchConfiguration)
    {
        $phraseEnabled = !(empty($searchConfiguration['query.']['phrase']) || $searchConfiguration['query.']['phrase'] !== 1);
        $phraseSlopConfigured = !empty($searchConfiguration['query.']['phrase.']['slop']);
        return  ($phraseEnabled && $phraseSlopConfigured) ? $searchConfiguration['query.']['phrase.']['slop'] : self::NO_SLOP;
    }

    /**
     * @param array $searchConfiguration
     * @return int|null
     */
    protected static function getQuerySlopFromConfiguration($searchConfiguration)
    {
        $phraseEnabled = !(empty($searchConfiguration['query.']['phrase']) || $searchConfiguration['query.']['phrase'] !== 1);
        $querySlopConfigured = !empty($searchConfiguration['query.']['phrase.']['querySlop']);
        return ($phraseEnabled && $querySlopConfigured) ? $searchConfiguration['query.']['phrase.']['querySlop'] : self::NO_SLOP;
    }

    /**
     * @param array $searchConfiguration
     * @return int|null
     */
    protected static function getBigramPhraseSlopFromConfiguration($searchConfiguration)
    {
        $bigramPhraseEnabled = !empty($searchConfiguration['query.']['bigramPhrase']) && $searchConfiguration['query.']['bigramPhrase'] === 1;
        $bigramSlopConfigured = !empty($searchConfiguration['query.']['bigramPhrase.']['slop']);
        return ($bigramPhraseEnabled && $bigramSlopConfigured) ? $searchConfiguration['query.']['bigramPhrase.']['slop'] : self::NO_SLOP;
    }

    /**
     * @param array $searchConfiguration
     * @return int|null
     */
    protected static function getTrigramPhraseSlopFromConfiguration($searchConfiguration)
    {
        $trigramPhraseEnabled = !empty($searchConfiguration['query.']['trigramPhrase']) && $searchConfiguration['query.']['trigramPhrase'] === 1;
        $trigramSlopConfigured = !empty($searchConfiguration['query.']['trigramPhrase.']['slop']);
        return ($trigramPhraseEnabled && $trigramSlopConfigured) ? $searchConfiguration['query.']['trigramPhrase.']['slop'] : self::NO_SLOP;
    }
}