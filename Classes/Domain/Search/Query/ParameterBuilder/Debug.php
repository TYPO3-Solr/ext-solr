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


/**
 * The Debug ParameterProvider is responsible to build the solr query parameters
 * that are needed for the debugging.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder
 */
class Debug extends AbstractDeactivatableParameterBuilder implements ParameterBuilder
{
    /**
     * Debug constructor.
     *
     * @param bool $isEnabled
     */
    public function __construct($isEnabled)
    {
        $this->isEnabled = $isEnabled;
    }

    /**
     * @param Query $query
     * @return Query
     */
    public function build(Query $query): Query
    {
        if (!$this->isEnabled) {
            $query->getQueryParametersContainer()->removeMany(['debugQuery', 'echoParams']);
            return $query;
        }

        $query->getQueryParametersContainer()->merge(['debugQuery' => 'true', 'echoParams' => 'all']);
        return $query;
    }

    /**
     * @return Debug
     */
    public static function getEmpty()
    {
        return new Debug(false);
    }
}