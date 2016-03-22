<?php
namespace ApacheSolrForTypo3\Solr\ResultDocumentModifier;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Plugin\Results\ResultsCommand;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Template;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Highlighting result document modifier, highlights query terms in result
 * documents.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class DocumentHighlighter implements ResultDocumentModifier
{

    /**
     * @var Search
     */
    protected $search;

    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * @var array
     */
    protected $highlightFields;

    /**
     * @var string
     */
    protected $fragmentSeparator;

    /**
     *
     */
    public function __construct()
    {
        $this->configuration = Util::getSolrConfiguration();
        $this->highlightFields = $this->configuration->getSearchResultsHighlightingFieldsAsArray();
        $this->fragmentSeparator = $this->configuration->getSearchResultsHighlightingFragmentSeparator();
    }

    /**
     * Modifies the given document and returns the modified document as result.
     *
     * @param ResultsCommand $resultCommand The search result command
     * @param array $resultDocument Result document as array
     * @return array The document with fields as array
     */
    public function modifyResultDocument(
        ResultsCommand $resultCommand,
        array $resultDocument
    ) {
        $this->search = $resultCommand->getParentPlugin()->getSearchResultSetService()->getSearch();

        $highlightedContent = $this->search->getHighlightedContent();
        foreach ($this->highlightFields as $highlightField) {
            if (!empty($highlightedContent->{$resultDocument['id']}->{$highlightField}[0])) {
                $fragments = array();
                foreach ($highlightedContent->{$resultDocument['id']}->{$highlightField} as $fragment) {
                    $fragments[] = Template::escapeMarkers($fragment);
                }
                $resultDocument[$highlightField] = implode(
                    ' ' . $this->fragmentSeparator . ' ',
                    $fragments
                );
            }
        }

        return $resultDocument;
    }
}
