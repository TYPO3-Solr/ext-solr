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

namespace ApacheSolrForTypo3\Solr\System\Page;

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
