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
