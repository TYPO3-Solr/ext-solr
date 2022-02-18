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
 * The Elevation ParameterProvider is responsible to build the solr query parameters
 * that are needed for the elevation.
 */
class Elevation extends AbstractDeactivatable implements ParameterBuilder
{
    /**
     * @var bool
     */
    protected $isForced = true;

    /**
     * @var bool
     */
    protected $markElevatedResults = true;

    /**
     * Elevation constructor.
     * @param boolean $isEnabled
     * @param boolean $isForced
     * @param boolean $markElevatedResults
     */
    public function __construct($isEnabled = false, $isForced = true, $markElevatedResults = true)
    {
        $this->isEnabled = $isEnabled;
        $this->isForced = $isForced;
        $this->markElevatedResults = $markElevatedResults;
    }

    /**
     * @return boolean
     */
    public function getIsForced(): bool
    {
        return $this->isForced;
    }

    /**
     * @param boolean $isForced
     */
    public function setIsForced(bool $isForced)
    {
        $this->isForced = $isForced;
    }

    /**
     * @return boolean
     */
    public function getMarkElevatedResults(): bool
    {
        return $this->markElevatedResults;
    }

    /**
     * @param boolean $markElevatedResults
     */
    public function setMarkElevatedResults(bool $markElevatedResults)
    {
        $this->markElevatedResults = $markElevatedResults;
    }

    /**
     * @param TypoScriptConfiguration $solrConfiguration
     * @return Elevation
     */
    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration)
    {
        $isEnabled = $solrConfiguration->getSearchElevation();
        if (!$isEnabled) {
            return new Elevation(false);
        }

        $force = $solrConfiguration->getSearchElevationForceElevation();
        $markResults = $solrConfiguration->getSearchElevationMarkElevatedResults();
        return new Elevation(true, $force, $markResults);
    }

    /**
     * @return Elevation
     */
    public static function getEmpty()
    {
        return new Elevation(false);
    }

    /**
     * @param AbstractQueryBuilder $parentBuilder
     * @return AbstractQueryBuilder
     */
    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $query = $parentBuilder->getQuery();
        if (!$this->getIsEnabled()) {
            $query->addParam('enableElevation', null);
            $query->addParam('forceElevation', null);
            $query->removeField('isElevated:[elevated]');
            return $parentBuilder;
        }

        $query->addParam('enableElevation', 'true');
        $forceElevationString = $this->getIsForced() ? 'true' : 'false';
        $query->addParam('forceElevation', $forceElevationString);

        if ($this->getMarkElevatedResults()) {
            $query->addField('isElevated:[elevated]');
        }

        return $parentBuilder;
    }
}
