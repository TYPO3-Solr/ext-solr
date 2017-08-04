<?php
namespace ApacheSolrForTypo3\Solr\Mvc\Controller;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;

/**
 * Class SolrControllerContext
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SolrControllerContext extends ControllerContext
{

    /**
     * @var TypoScriptConfiguration
     */
    protected $typoScriptConfiguration;

    /**
     * @var SearchResultSet
     */
    protected $searchResultSet;

    /**
     * @param TypoScriptConfiguration $typoScriptConfiguration
     */
    public function setTypoScriptConfiguration(TypoScriptConfiguration $typoScriptConfiguration)
    {
        $this->typoScriptConfiguration = $typoScriptConfiguration;
    }

    /**
     * @return TypoScriptConfiguration
     */
    public function getTypoScriptConfiguration()
    {
        return $this->typoScriptConfiguration;
    }

    /**
     * @param SearchResultSet $searchResultSet
     */
    public function setSearchResultSet(SearchResultSet $searchResultSet)
    {
        $this->searchResultSet = $searchResultSet;
    }

    /**
     * @return SearchResultSet
     */
    public function getSearchResultSet()
    {
        return $this->searchResultSet;
    }
}
