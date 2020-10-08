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

namespace ApacheSolrForTypo3\Solr\Event\Routing;

/**
 * This event will triggered before process variable keys and values
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class BeforeProcessCachedVariablesEvent
{
    /**
     * List of variable keys
     *
     * @var array
     */
    protected $variableKeys = [];

    /**
     * List of variable values
     *
     * @var array
     */
    protected $variableValues = [];

    /**
     * BeforeReplaceVariableInCachedUrlEvent constructor.
     * @param array $variableKeys
     * @param array $variableValues
     */
    public function __construct(array $variableKeys, array $variableValues)
    {
        $this->variableKeys = $variableKeys;
        $this->variableValues = $variableValues;
    }

    /**
     * Returns the variable keys
     *
     * @return array
     */
    public function getVariableKeys(): array
    {
        return $this->variableKeys;
    }

    /**
     * Sets the variable keys
     *
     * @param array $variableKeys
     * @return $this
     */
    public function setVariableKeys(array $variableKeys): self
    {
        $this->variableKeys = $variableKeys;
        return $this;
    }

    /**
     * Returns the variable values
     *
     * @return array
     */
    public function getVariableValues(): array
    {
        return $this->variableValues;
    }

    /**
     * Sets the variable values
     *
     * @param array $variableValues
     * @return $this
     */
    public function setVariableValues(array $variableValues): self
    {
        $this->variableValues = $variableValues;
        return $this;
    }
}