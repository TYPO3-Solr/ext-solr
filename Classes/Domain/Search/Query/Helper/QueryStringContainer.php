<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
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

class QueryStringContainer {

    /**
     * @var string
     */
    protected $keywords;

    /**
     * @var string
     */
    protected $keywordsRaw;

    /**
     * @var
     */
    protected $queryString;

    /**
     * @var bool
     */
    private $rawQueryString = false;

    /**
     * QueryStrings constructor.
     * @param string $keywords
     */
    public function __construct($keywords)
    {
        $this->setKeywords($keywords);
    }

    /**
     * Builds the query string which is then used for Solr's q parameters
     *
     * @return string Solr query string
     */
    public function getQueryString()
    {
        if (!$this->rawQueryString) {
            $this->buildQueryString();
        }

        return $this->queryString;
    }

    /**
     * Sets the query string without any escaping.
     *
     * Be cautious with this function!
     * TODO remove this method as it basically just sets the q parameter / keywords
     *
     * @param string $queryString The raw query string.
     */
    public function setQueryString($queryString)
    {
        $this->queryString = $queryString;
    }

    /**
     * Creates the string that is later used as the q parameter in the solr query
     *
     * @return void
     */
    protected function buildQueryString()
    {
        // very simple for now
        $this->queryString = $this->keywords;
    }

    /**
     * Sets whether a raw query sting should be used, that is, whether the query
     * string should be escaped or not.
     *
     * @param bool $useRawQueryString TRUE to use raw queries (like Lucene Query Language) or FALSE for regular, escaped queries
     */
    public function useRawQueryString($useRawQueryString)
    {
        $this->rawQueryString = (boolean)$useRawQueryString;
    }

    /**
     * Get the query keywords, keywords are escaped.
     *
     * @return string query keywords
     */
    public function getKeywords()
    {
        return $this->keywords;
    }

    /**
     * Sets the query keywords, escapes them as needed for Solr/Lucene.
     *
     * @param string $keywords user search terms/keywords
     */
    public function setKeywords($keywords)
    {
        $this->keywords = EscapeService::escape($keywords);
        $this->keywordsRaw = $keywords;
    }

    /**
     * Gets the cleaned keywords so that it can be used in templates f.e.
     *
     * @return string The cleaned keywords.
     */
    public function getKeywordsCleaned()
    {
        return EscapeService::clean($this->keywordsRaw);
    }

    /**
     * Gets the raw, unescaped, unencoded keywords.
     *
     * USE WITH CAUTION!
     *
     * @return string raw keywords
     */
    public function getKeywordsRaw()
    {
        return $this->keywordsRaw;
    }

    /**
     * returns a string representation of the query
     *
     * @return string the string representation of the query
     */
    public function __toString()
    {
        return $this->getQueryString();
    }
}