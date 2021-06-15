<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Marc Bastian Heinrichs <mbh@mbh-software.de>
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
 * DomainObject observer registry
 */
class DomainObjectObserverRegistry implements \TYPO3\CMS\Core\SingletonInterface
{

    /**
     * @var array
     */
    protected $domainObjectClassNames = [];

    /**
     *
     * @param string $domainObjectClassName
     */
    public function register(
        $domainObjectClassName
    ) {
        if (!is_subclass_of($domainObjectClassName, \TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject::class)) {
            throw new \LogicException($domainObjectClassName . ' must be a subclass of AbstractDomainObject', 1519394042);
        }

        $this->domainObjectClassNames[$domainObjectClassName] = $domainObjectClassName;
    }

    /**
     * @param string $domainObjectClassName
     * @return bool
     */
    public function isRegistered($domainObjectClassName)
    {
        return isset($this->domainObjectClassNames[$domainObjectClassName]);
    }

    /**
     *
     * @return array
     */
    public function getAll()
    {
        return $this->domainObjectClassNames;
    }
}
