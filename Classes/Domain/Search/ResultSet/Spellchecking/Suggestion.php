<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Spellchecking;

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

/**
 * Value object that represent a spellchecking suggestion.
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class Suggestion
{

    /**
     * @var string
     */
    protected $suggestion = '';

    /**
     * @var string
     */
    protected $missSpelled = '';

    /**
     * @var int
     */
    protected $numFound = 1;

    /**
     * @var int
     */
    protected $startOffset = 0;

    /**
     * @var int
     */
    protected $endOffset = 0;

     /**
     * @param string $suggestion the suggested term
     * @param string $missSpelled the misspelled original term
     * @param int $numFound
     * @param int $startOffset
     * @param int $endOffset
     */
    public function __construct($suggestion = '', $missSpelled = '', $numFound = 1, $startOffset = 0, $endOffset = 0)
    {
        $this->suggestion = $suggestion;
        $this->missSpelled = $missSpelled;
        $this->numFound = $numFound;
        $this->startOffset = $startOffset;
        $this->endOffset = $endOffset;
    }

    /**
     * @return int
     */
    public function getEndOffset()
    {
        return $this->endOffset;
    }

    /**
     * @return int
     */
    public function getNumFound()
    {
        return $this->numFound;
    }

    /**
     * @return int
     */
    public function getStartOffset()
    {
        return $this->startOffset;
    }

    /**
     * @return string
     */
    public function getSuggestion()
    {
        return $this->suggestion;
    }

    /**
     * @return string
     */
    public function getMissSpelled()
    {
        return $this->missSpelled;
    }
}
