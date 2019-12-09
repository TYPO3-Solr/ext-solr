<?php

namespace ApacheSolrForTypo3\Solr\System\Page;

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

use ApacheSolrForTypo3\Solr\Domain\Site\Site;

/**
 * Rootline class. This class is used to perform operations on a rootline array.
 * The constructor requires an rootline array as an arguments (as you get it from
 * PageRepository::getRootline or TSFE->rootline.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class Rootline
{
    /**
     * @var array
     */
    protected $rootLineArray = [];

    /**
     * Rootline constructor.
     * @param array $rootLineArray
     */
    public function __construct(array $rootLineArray = [])
    {
        $this->rootLineArray = $rootLineArray;
    }

    /**
     * @return array
     */
    public function getRootLineArray()
    {
        return $this->rootLineArray;
    }

    /**
     * @param array $rootLineArray
     */
    public function setRootLineArray($rootLineArray)
    {
        $this->rootLineArray = $rootLineArray;
    }

    /**
     * Returns true if the rooline contains a root page.
     *
     * @return boolean
     */
    public function getHasRootPage()
    {
        return $this->getRootPageId() !== 0;
    }

    /**
     * Returns the rootPageId as integer if a rootpage is given,
     * if non is given 0 will be returned
     *
     * @return integer
     */
    public function getRootPageId()
    {
        $rootPageId = 0;

        if (empty($this->rootLineArray)) {
            return $rootPageId;
        }

        foreach ($this->rootLineArray as $page) {
            if (Site::isRootPage($page)) {
                $rootPageId = $page['uid'];
                break;
            }
        }

        return $rootPageId;
    }

    /**
     * Returns an array of the pageUids in the rootline.
     *
     * @return array
     */
    public function getParentPageIds()
    {
        $rootLineParentPageIds = [];
        if (empty($this->rootLineArray)) {
            // no rootline given
            return $rootLineParentPageIds;
        }

        foreach ($this->rootLineArray as $pageRecord) {
            $rootLineParentPageIds[] = $pageRecord['uid'];
            if (Site::isRootPage($pageRecord)) {
                break;
            }
        }

        return $rootLineParentPageIds;
    }
}
