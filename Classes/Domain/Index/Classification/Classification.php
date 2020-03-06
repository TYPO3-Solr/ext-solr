<?php declare(strict_types = 1);
namespace ApacheSolrForTypo3\Solr\Domain\Index\Classification;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
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
 * Class Classification
 */
class Classification {

    /**
     * Array of regular expressions
     *
     * @var array
     */
    protected $matchPatterns = [];

    /**
     * Array of regular expressions
     * @var array
     */
    protected $unMatchPatterns = [];

    /**
     * @var string
     */
    protected $mappedClass = '';

    /**
     * Classification constructor.
     * @param array $matchPatterns
     * @param array $unMatchPatterns
     * @param string $mappedClass
     */
    public function __construct(array $matchPatterns = [], array $unMatchPatterns = [],string $mappedClass = '')
    {
        $this->matchPatterns = $matchPatterns;
        $this->unMatchPatterns = $unMatchPatterns;
        $this->mappedClass = $mappedClass;
    }

    /**
     * @return array
     */
    public function getUnMatchPatterns(): array
    {
        return $this->unMatchPatterns;
    }

    /**
     * @param array $unMatchPatterns
     */
    public function setUnMatchPatterns(array $unMatchPatterns)
    {
        $this->unMatchPatterns = $unMatchPatterns;
    }

    /**
     * @return array
     */
    public function getMatchPatterns(): array
    {
        return $this->matchPatterns;
    }

    /**
     * @param array $matchPatterns
     */
    public function setMatchPatterns(array $matchPatterns)
    {
        $this->matchPatterns = $matchPatterns;
    }

    /**
     * @return string
     */
    public function getMappedClass(): string
    {
        return $this->mappedClass;
    }

    /**
     * @param string $mappedClass
     */
    public function setMappedClass(string $mappedClass)
    {
        $this->mappedClass = $mappedClass;
    }
}
