<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\Score;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
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

/**
 * Holds the data of an analyzed score.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class Score
{
    /**
     * @var float
     */
    protected $score = 0.0;

    /**
     * @var string
     */
    protected $searchTerm = '';

    /**
     * @var float
     */
    protected $boost = 0.0;

    /**
     * @var string
     */
    protected $fieldName = '';

    /**
     * @param float $boost
     * @param string $fieldName
     * @param float $score
     * @param string $searchTerm
     */
    public function __construct($boost, $fieldName, $score, $searchTerm)
    {
        $this->boost = $boost;
        $this->fieldName = $fieldName;
        $this->score = $score;
        $this->searchTerm = $searchTerm;
    }

    /**
     * @return float
     */
    public function getBoost()
    {
        return $this->boost;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @return float
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * @return string
     */
    public function getSearchTerm()
    {
        return $this->searchTerm;
    }
}
