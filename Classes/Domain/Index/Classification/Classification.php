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
     */
    protected array $matchPatterns = [];

    /**
     * Array of regular expressions
     */
    protected array $unMatchPatterns = [];

    protected string $mappedClass;

    /**
     * Classification constructor.
     */
    public function __construct(
        array $matchPatterns = [],
        array $unMatchPatterns = [],
        string $mappedClass = '',
    ) {
        $this->matchPatterns = $matchPatterns;
        $this->unMatchPatterns = $unMatchPatterns;
        $this->mappedClass = $mappedClass;
    }

    public function getUnMatchPatterns(): array
    {
        return $this->unMatchPatterns;
    }

    public function setUnMatchPatterns(array $unMatchPatterns): void
    {
        $this->unMatchPatterns = $unMatchPatterns;
    }

    public function getMatchPatterns(): array
    {
        return $this->matchPatterns;
    }

    public function setMatchPatterns(array $matchPatterns): void
    {
        $this->matchPatterns = $matchPatterns;
    }

    public function getMappedClass(): string
    {
        return $this->mappedClass;
    }

    public function setMappedClass(string $mappedClass): void
    {
        $this->mappedClass = $mappedClass;
    }
}
