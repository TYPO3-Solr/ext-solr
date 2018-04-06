<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 <timo.hund@dkd.de>
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
 * The Elevation ParameterProvider is responsible to build the solr query parameters
 * that are needed for the elevation.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder
 */
class Elevation extends AbstractDeactivatableParameterBuilder implements ParameterBuilder
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
     * @param Query $query
     * @return Query
     */
    public function build(Query $query): Query
    {
        if (!$this->isEnabled) {
            $query->getQueryParametersContainer()->remove('enableElevation');
            $query->getQueryParametersContainer()->remove('forceElevation');
            $query->getReturnFields()->remove('isElevated:[elevated]');
            $query->getReturnFields()->remove('[elevated]'); // fallback

            return $query;
        }

        $query->getQueryParametersContainer()->set('enableElevation', 'true');
        $forceElevationString = $this->isForced ? 'true' : 'false';
        $query->getQueryParametersContainer()->set('forceElevation', $forceElevationString);
        if ($this->markElevatedResults) {
            $query->getReturnFields()->add('isElevated:[elevated]');
        }

        return $query;
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
}