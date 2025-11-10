<?php

declare(strict_types=1);

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
class Slops implements ParameterBuilderInterface
{
    public const NO_SLOP = null;

    /**
     * Represents the Apache Solr parameter: qs
     * See: https://solr.apache.org/guide/solr/latest/query-guide/dismax-query-parser.html#qs-query-phrase-slop-parameter
     */
    protected ?int $querySlop = self::NO_SLOP;

    /**
     * Represents the Apache Solr parameter: ps
     * See: https://solr.apache.org/guide/solr/latest/query-guide/edismax-query-parser.html
     */
    protected ?int $phraseSlop = self::NO_SLOP;

    /**
     * Represents the Apache Solr parameter: ps2
     * See: https://solr.apache.org/guide/solr/latest/query-guide/edismax-query-parser.html
     */
    protected ?int $bigramPhraseSlop = self::NO_SLOP;

    /**
     * Represents the Apache Solr parameter: ps3
     * See: https://solr.apache.org/guide/solr/latest/query-guide/edismax-query-parser.html
     */
    protected ?int $trigramPhraseSlop = self::NO_SLOP;

    public function __construct(
        ?int $querySlop = self::NO_SLOP,
        ?int $phraseSlop = self::NO_SLOP,
        ?int $bigramPhraseSlop = self::NO_SLOP,
        ?int $trigramPhraseSlop = self::NO_SLOP,
    ) {
        $this->querySlop = $querySlop;
        $this->phraseSlop = $phraseSlop;
        $this->bigramPhraseSlop = $bigramPhraseSlop;
        $this->trigramPhraseSlop = $trigramPhraseSlop;
    }

    public function getHasQuerySlop(): bool
    {
        return $this->querySlop !== null;
    }

    public function getQuerySlop(): ?int
    {
        return $this->querySlop;
    }

    public function setQuerySlop(int $querySlop): void
    {
        $this->querySlop = $querySlop;
    }

    public function getHasPhraseSlop(): bool
    {
        return $this->phraseSlop !== null;
    }

    public function getPhraseSlop(): ?int
    {
        return $this->phraseSlop;
    }

    public function setPhraseSlop(int $phraseSlop): void
    {
        $this->phraseSlop = $phraseSlop;
    }

    public function getHasBigramPhraseSlop(): bool
    {
        return $this->bigramPhraseSlop !== null;
    }

    public function getBigramPhraseSlop(): ?int
    {
        return $this->bigramPhraseSlop;
    }

    public function setBigramPhraseSlop(int $bigramPhraseSlop): void
    {
        $this->bigramPhraseSlop = $bigramPhraseSlop;
    }

    public function getHasTrigramPhraseSlop(): bool
    {
        return $this->trigramPhraseSlop !== null;
    }

    public function getTrigramPhraseSlop(): ?int
    {
        return $this->trigramPhraseSlop;
    }

    public function setTrigramPhraseSlop(int $trigramPhraseSlop): void
    {
        $this->trigramPhraseSlop = $trigramPhraseSlop;
    }

    /**
     * Instantiates Slops from TypoScript configuration.
     */
    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration): Slops
    {
        $searchConfiguration = $solrConfiguration->getSearchConfiguration();
        $querySlop = static::getQuerySlopFromConfiguration($searchConfiguration);
        $phraseSlop = static::getPhraseSlopFromConfiguration($searchConfiguration);
        $bigramPhraseSlop = static::getBigramPhraseSlopFromConfiguration($searchConfiguration);
        $trigramPhraseSlop = static::getTrigramPhraseSlopFromConfiguration($searchConfiguration);
        return new Slops($querySlop, $phraseSlop, $bigramPhraseSlop, $trigramPhraseSlop);
    }

    protected static function getPhraseSlopFromConfiguration(array $searchConfiguration): ?int
    {
        $phraseEnabled = !(empty($searchConfiguration['query.']['phrase']) || (int)$searchConfiguration['query.']['phrase'] !== 1);
        $phraseSlopConfigured = !empty($searchConfiguration['query.']['phrase.']['slop']);
        return ($phraseEnabled && $phraseSlopConfigured) ? (int)$searchConfiguration['query.']['phrase.']['slop'] : self::NO_SLOP;
    }

    protected static function getQuerySlopFromConfiguration(array $searchConfiguration): ?int
    {
        $phraseEnabled = !(empty($searchConfiguration['query.']['phrase']) || (int)$searchConfiguration['query.']['phrase'] !== 1);
        $querySlopConfigured = !empty($searchConfiguration['query.']['phrase.']['querySlop']);
        return ($phraseEnabled && $querySlopConfigured) ? (int)$searchConfiguration['query.']['phrase.']['querySlop'] : self::NO_SLOP;
    }

    protected static function getBigramPhraseSlopFromConfiguration(array $searchConfiguration): ?int
    {
        $bigramPhraseEnabled = !empty($searchConfiguration['query.']['bigramPhrase']) && (int)$searchConfiguration['query.']['bigramPhrase'] === 1;
        $bigramSlopConfigured = !empty($searchConfiguration['query.']['bigramPhrase.']['slop']);
        return ($bigramPhraseEnabled && $bigramSlopConfigured) ? (int)$searchConfiguration['query.']['bigramPhrase.']['slop'] : self::NO_SLOP;
    }

    protected static function getTrigramPhraseSlopFromConfiguration(array $searchConfiguration): ?int
    {
        $trigramPhraseEnabled = !empty($searchConfiguration['query.']['trigramPhrase']) && (int)$searchConfiguration['query.']['trigramPhrase'] === 1;
        $trigramSlopConfigured = !empty($searchConfiguration['query.']['trigramPhrase.']['slop']);
        return ($trigramPhraseEnabled && $trigramSlopConfigured) ? (int)$searchConfiguration['query.']['trigramPhrase.']['slop'] : self::NO_SLOP;
    }

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
