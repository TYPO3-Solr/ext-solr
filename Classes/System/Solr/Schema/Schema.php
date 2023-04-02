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
    protected string $managedResourceId = 'core_en';

    protected string $name = '';

    public function getManagedResourceId(): string
    {
        return $this->managedResourceId;
    }

    public function setManagedResourceId(string $managedResourceId): void
    {
        $this->managedResourceId = $managedResourceId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
