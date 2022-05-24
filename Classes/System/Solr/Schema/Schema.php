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

namespace ApacheSolrForTypo3\Solr\System\Solr\Schema;

/**
 * Object representation of the solr schema.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class Schema
{
    /**
     * @var string
     */
    protected $managedResourceId = 'core_en';

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @return string
     */
    public function getManagedResourceId(): string
    {
        return $this->managedResourceId;
    }

    /**
     * @param string $managedResourceId
     */
    public function setManagedResourceId(string $managedResourceId)
    {
        $this->managedResourceId = $managedResourceId;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }
}
