<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

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


// TODO use/extend ApacheSolrForTypo3\Solr\IndexQueue\AbstractIndexer
use ApacheSolrForTypo3\Solr\IndexQueue\AbstractIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\InvalidFieldNameException;
use ApacheSolrForTypo3\Solr\SubstitutePageIndexer;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Indexer to add / overwrite page document fields as defined in
 * plugin.tx_solr.index.queue.pages.fields.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class PageFieldMappingIndexer implements SubstitutePageIndexer
{
    /**
     * @var \ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * @var array
     */
    protected $pageIndexingConfiguration = array();

    /**
     * @var array
     */
    protected $mappedFieldNames = array();

    /**
     * @param TypoScriptConfiguration $configuration
     */
    public function __construct(TypoScriptConfiguration $configuration = null)
    {
        $this->configuration = $configuration == null ? Util::getSolrConfiguration() : $configuration;
        $this->pageIndexingConfiguration = $this->configuration->getIndexQueueFieldsConfigurationByConfigurationName('pages');
        $this->mappedFieldNames = $this->configuration->getIndexQueueMappedFieldsByConfigurationName('pages');
    }

    /**
     * Returns a substitute document for the currently being indexed page.
     *
     * Uses the original document and adds fields as defined in
     * plugin.tx_solr.index.queue.pages.fields.
     *
     * @param \Apache_Solr_Document $pageDocument The original page document.
     * @return \Apache_Solr_Document A Apache_Solr_Document object that replace the default page document
     */
    public function getPageDocument(\Apache_Solr_Document $pageDocument)
    {
        $substitutePageDocument = clone $pageDocument;
        $mappedFields = $this->getMappedFields();
        foreach ($mappedFields as $fieldName => $fieldValue) {
            if (isset($substitutePageDocument->{$fieldName})) {
                // reset = overwrite, especially important to not make fields
                // multi valued where they may not accept multiple values
                unset($substitutePageDocument->{$fieldName});
            }

            // add new field / overwrite field if it was set before
            if ($fieldValue !== '' && $fieldValue !== null) {
                $substitutePageDocument->setField($fieldName, $fieldValue);
            }
        }

        return $substitutePageDocument;
    }

    /**
     * Gets the mapped fields as an array mapping field names to values.
     *
     * @throws InvalidFieldNameException
     * @return array An array mapping field names to their values.
     */
    protected function getMappedFields()
    {
        $fields = array();

        foreach ($this->mappedFieldNames as $mappedFieldName) {
            if (!AbstractIndexer::isAllowedToOverrideField($mappedFieldName)) {
                throw new InvalidFieldNameException(
                    'Must not overwrite field "type".',
                    1435441863
                );
            }
            $fields[$mappedFieldName] = $this->resolveFieldValue($mappedFieldName);
        }

        return $fields;
    }

    /**
     * Resolves a field mapping to its value depending on its configuration.
     *
     * Allows to put the page record through cObj processing if wanted / needed.
     * Otherwise the plain page record field value is used.
     *
     * @param string $solrFieldName The Solr field name to resolve the value from the item's record
     * @return string The resolved string value to be indexed
     */
    protected function resolveFieldValue($solrFieldName)
    {
        $fieldValue = '';
        $pageRecord = $GLOBALS['TSFE']->page;

        if (isset($this->pageIndexingConfiguration[$solrFieldName . '.'])) {
            // configuration found => need to resolve a cObj
            $contentObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
            $contentObject->start($pageRecord, 'pages');

            $fieldValue = $contentObject->cObjGetSingle(
                $this->pageIndexingConfiguration[$solrFieldName],
                $this->pageIndexingConfiguration[$solrFieldName . '.']
            );

            if (AbstractIndexer::isSerializedValue($this->pageIndexingConfiguration,
                $solrFieldName)
            ) {
                $fieldValue = unserialize($fieldValue);
            }
        } else {
            $fieldValue = $pageRecord[$this->pageIndexingConfiguration[$solrFieldName]];
        }

        return $fieldValue;
    }
}
