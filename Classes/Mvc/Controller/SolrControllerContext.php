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

namespace ApacheSolrForTypo3\Solr\Mvc\Controller;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

/**
 * Class SolrControllerContext
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SolrControllerContext
{
    /**
     * @var TypoScriptConfiguration|null
     */
    protected ?TypoScriptConfiguration $typoScriptConfiguration = null;

    /**
     * @var SearchResultSet|null
     */
    protected ?SearchResultSet $searchResultSet = null;

    /**
     * @param TypoScriptConfiguration $typoScriptConfiguration
     */
    public function setTypoScriptConfiguration(TypoScriptConfiguration $typoScriptConfiguration): void
    {
        $this->typoScriptConfiguration = $typoScriptConfiguration;
    }

    /**
     * @return TypoScriptConfiguration|null
     */
    public function getTypoScriptConfiguration(): ?TypoScriptConfiguration
    {
        return $this->typoScriptConfiguration;
    }

    /**
     * @param SearchResultSet $searchResultSet
     */
    public function setSearchResultSet(SearchResultSet $searchResultSet): void
    {
        $this->searchResultSet = $searchResultSet;
    }

    /**
     * @return SearchResultSet|null
     */
    public function getSearchResultSet(): ?SearchResultSet
    {
        return $this->searchResultSet;
    }

    public function getUriBuilder(): UriBuilder
    {
        return GeneralUtility::makeInstance(UriBuilder::class);
    }
}
