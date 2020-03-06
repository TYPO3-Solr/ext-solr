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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\AbstractQueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The Highlighting ParameterProvider is responsible to build the solr query parameters
 * that are needed for the highlighting.
 */
class Highlighting extends AbstractDeactivatable implements ParameterBuilder
{
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
     * @param bool $isEnabled
     * @param int $fragmentSize
     * @param string $highlightingFieldList
     * @param string $prefix
     * @param string $postfix
     */
    public function __construct($isEnabled = false, $fragmentSize = 200, $highlightingFieldList = '', $prefix = '', $postfix = '')
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
     * @return bool
     */
    public function getUseFastVectorHighlighter()
    {
        return ($this->fragmentSize >= 18);
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

    /**
     * @return Highlighting
     */
    public static function getEmpty()
    {
        return new Highlighting(false);
    }


    /**
     * @param AbstractQueryBuilder $parentBuilder
     * @return AbstractQueryBuilder
     */
    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $query = $parentBuilder->getQuery();
        if(!$this->getIsEnabled()) {
            $query->removeComponent($query->getHighlighting());
            return $parentBuilder;
        }

        $query->getHighlighting()->setFragSize($this->getFragmentSize());
        $query->getHighlighting()->setFields(GeneralUtility::trimExplode(",", $this->getHighlightingFieldList()));

        if ($this->getUseFastVectorHighlighter()) {
            $query->getHighlighting()->setUseFastVectorHighlighter(true);
            $query->getHighlighting()->setTagPrefix($this->getPrefix());
            $query->getHighlighting()->setTagPostfix($this->getPostfix());
        } else {
            $query->getHighlighting()->setUseFastVectorHighlighter(false);
            $query->getHighlighting()->setTagPrefix(null);
            $query->getHighlighting()->setTagPostfix(null);
        }

        if ($this->getPrefix() !== '' && $this->getPostfix() !== '') {
            $query->getHighlighting()->setSimplePrefix($this->getPrefix());
            $query->getHighlighting()->setSimplePostfix($this->getPostfix());
        }

        return $parentBuilder;
    }
}
