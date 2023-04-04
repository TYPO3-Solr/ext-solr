<?php

namespace ApacheSolrForTypo3\Solr\System\Solr\Schema;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2016 Timo Hund <timo.hund@dkd.de
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
