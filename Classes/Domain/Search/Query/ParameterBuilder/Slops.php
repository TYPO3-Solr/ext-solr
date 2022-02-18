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
 * The Slops ParameterProvider is responsible to build the solr query parameters
 * that are needed for the several slop arguments.
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
     * @return boolean
     */
    public function getHasQuerySlop()
    {
        return $this->querySlop !== null;
    }

    /**
     * @return int|null
     */
    public function getQuerySlop()
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
     * @return boolean
     */
    public function getHasPhraseSlop()
    {
        return $this->phraseSlop !== null;
    }


    /**
     * @return int|null
     */
    public function getPhraseSlop()
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
     * @return boolean
     */
    public function getHasBigramPhraseSlop()
    {
        return $this->bigramPhraseSlop !== null;
    }

    /**
     * @return int|null
     */
    public function getBigramPhraseSlop()
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
     * @return boolean
     */
    public function getHasTrigramPhraseSlop()
    {
        return $this->trigramPhraseSlop !== null;
    }

    /**
     * @return int|null
     */
    public function getTrigramPhraseSlop()
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

    /**
     * @param AbstractQueryBuilder $parentBuilder
     * @return AbstractQueryBuilder
     */
    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $query = $parentBuilder->getQuery();

        if ($this->getHasPhraseSlop()) {
            $query->getEDisMax()->setPhraseSlop($this->getPhraseSlop());
        }

        if ($this->getHasBigramPhraseSlop()) {
            $query->getEDisMax()->setPhraseBigramSlop($this->getBigramPhraseSlop());
        }

        if ($this->getHasTrigramPhraseSlop()) {
            $query->getEDisMax()->setPhraseTrigramSlop($this->getTrigramPhraseSlop());
        }

        if ($this->getHasQuerySlop()) {
            $query->getEDisMax()->setQueryPhraseSlop($this->getQuerySlop());
        }

        return $parentBuilder;
    }
}
