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

namespace ApacheSolrForTypo3\Solr;

use Exception;

/**
 * Exception that is thrown when no Solr connection could be found.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class NoSolrConnectionFoundException extends Exception
{
    protected ?int $pageId = null;

    protected ?int $languageId = null;

    protected ?int $rootPageId = null;

    public function getPageId(): ?int
    {
        return $this->pageId;
    }

    public function setPageId($pageId)
    {
        $this->pageId = (int)$pageId;
    }

    public function getLanguageId(): ?int
    {
        return $this->languageId;
    }

    public function setLanguageId(int $languageId)
    {
        $this->languageId = $languageId;
    }

    public function getRootPageId(): ?int
    {
        return $this->rootPageId;
    }

    public function setRootPageId(int $rootPageId)
    {
        $this->rootPageId = $rootPageId;
    }
}
