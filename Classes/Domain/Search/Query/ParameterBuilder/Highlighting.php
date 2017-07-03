<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * The Highlighting ParameterProvider is responsible to build the solr query parameters
 * that are needed for the highlighting.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder
 */
class Highlighting implements ParameterBuilder
{

    /**
     * @var bool
     */
    protected $isEnabled = false;

    /**
     * @var int
     */
    protected $fragmentSize = 200;

    /**
     * @var string
     */
    protected $highlightingFieldList = '';

    /**
     * @var string
     */
    protected $prefix = '';

    /**
     * @var string
     */
    protected $postfix = '';

    /**
     * Highlighting constructor.
     *
     * private constructor should only be created with the from* methods
     *
     * @param bool $isEnabled
     * @param int $fragmentSize
     * @param string $highlightingFieldList
     * @param string $prefix
     * @param string $postfix
     */
    private function __construct($isEnabled = false, $fragmentSize = 200, $highlightingFieldList = '', $prefix = '', $postfix = '')
    {
        $this->isEnabled = $isEnabled;
        $this->fragmentSize = $fragmentSize;
        $this->highlightingFieldList = $highlightingFieldList;
        $this->prefix = $prefix;
        $this->postfix = $postfix;
    }

    /**
     * @return int
     */
    public function getFragmentSize(): int
    {
        return $this->fragmentSize;
    }

    /**
     * @param int $fragmentSize
     */
    public function setFragmentSize(int $fragmentSize)
    {
        $this->fragmentSize = $fragmentSize;
    }

    /**
     * @return string
     */
    public function getHighlightingFieldList(): string
    {
        return $this->highlightingFieldList;
    }

    /**
     * @param string $highlightingFieldList
     */
    public function setHighlightingFieldList(string $highlightingFieldList)
    {
        $this->highlightingFieldList = $highlightingFieldList;
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @param string $prefix
     */
    public function setPrefix(string $prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * @return string
     */
    public function getPostfix(): string
    {
        return $this->postfix;
    }

    /**
     * @param string $postfix
     */
    public function setPostfix(string $postfix)
    {
        $this->postfix = $postfix;
    }

    /**
     * @return boolean
     */
    public function getIsEnabled()
    {
        return $this->isEnabled;
    }

    /**
     * @param boolean $isEnabled
     */
    public function setIsEnabled($isEnabled)
    {
        $this->isEnabled = $isEnabled;
    }

    /**
     * @return array
     */
    public function build()
    {
        if (!$this->isEnabled) {
            return [];
        }

        $highlightingParameter = [];
        $highlightingParameter['hl'] = 'true';
        $highlightingParameter['hl.fragsize'] = (int)$this->fragmentSize;

        if ($this->highlightingFieldList != '') {
            $highlightingParameter['hl.fl'] = $this->highlightingFieldList;
        }

        // the fast vector highlighter can only be used, when the fragmentSize is
        // higher then 17 otherwise solr throws an exception
        $useFastVectorHighlighter = ($this->fragmentSize >= 18);

        if ($useFastVectorHighlighter) {
            $highlightingParameter['hl.useFastVectorHighlighter'] = 'true';
            $highlightingParameter['hl.tag.pre'] = $this->prefix;
            $highlightingParameter['hl.tag.post'] = $this->postfix;
        }

        if ($this->prefix !== '' && $this->postfix !== '') {
            $highlightingParameter['hl.simple.pre'] = $this->prefix;
            $highlightingParameter['hl.simple.post'] = $this->postfix;
        }

        return $highlightingParameter;
    }

    /**
     * @param TypoScriptConfiguration $solrConfiguration
     * @return Highlighting
     */
    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration)
    {
        $isEnabled = $solrConfiguration->getSearchResultsHighlighting();
        if (!$isEnabled) {
            return new Highlighting(false);
        }

        $fragmentSize = $solrConfiguration->getSearchResultsHighlightingFragmentSize();
        $highlightingFields = $solrConfiguration->getSearchResultsHighlightingFields();
        $wrap = explode('|', $solrConfiguration->getSearchResultsHighlightingWrap());
        $prefix = isset($wrap[0]) ? $wrap[0] : '';
        $postfix = isset($wrap[1]) ? $wrap[1] : '';


        return new Highlighting($isEnabled, $fragmentSize, $highlightingFields, $prefix, $postfix);
    }
}