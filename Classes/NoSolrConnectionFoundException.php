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

namespace ApacheSolrForTypo3\Solr;

use Exception;

/**
 * Exception that is thrown when no Solr connection could be found.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class NoSolrConnectionFoundException extends Exception
{
    protected $pageId;

    protected $languageId;

    protected $rootPageId;

    public function getPageId()
    {
        return $this->pageId;
    }

    public function setPageId($pageId)
    {
        $this->pageId = intval($pageId);
    }

    public function getLanguageId()
    {
        return $this->languageId;
    }

    public function setLanguageId($languageId)
    {
        $this->languageId = intval($languageId);
    }

    public function getRootPageId()
    {
        return $this->rootPageId;
    }

    public function setRootPageId($rootPageId)
    {
        $this->rootPageId = intval($rootPageId);
    }
}
