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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Classification;

/**
 * Class Classification
 */
class Classification
{
    /**
     * Array of regular expressions
     *
     * @var array
     */
    protected array $matchPatterns = [];

    /**
     * Array of regular expressions
     * @var array
     */
    protected array $unMatchPatterns = [];

    /**
     * @var string
     */
    protected string $mappedClass;

    /**
     * Classification constructor.
     * @param array $matchPatterns
     * @param array $unMatchPatterns
     * @param string $mappedClass
     */
    public function __construct(
        array $matchPatterns = [],
        array $unMatchPatterns = [],
        string $mappedClass = ''
    ) {
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
