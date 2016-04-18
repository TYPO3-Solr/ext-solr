<?php
namespace ApacheSolrForTypo3\Solr\Backend\IndexInspector;

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

use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Remote Controller to provide document data for the Index Inspector.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class IndexInspectorRemoteController
{

    /**
     * The current page ID.
     *
     * @var integer
     */
    protected $pageId = 0;

    /**
     * Search
     *
     * @var Search
     */
    protected $search = null;

    /**
     * Initialization method to be executed when receiving an ExtDirect call is
     * received.
     *
     * @param integer $pageId ID of the current page, the pages' table uid column
     * @throws \InvalidArgumentException if page ID is 0 or not an integer
     */
    protected function initialize($pageId)
    {
        if (empty($pageId) || !is_int($pageId)) {
            throw new \InvalidArgumentException('Invalid page ID.', 1303893535);
        }
        $this->pageId = $pageId;

        $this->initializeSearch();
    }

    /**
     * Initializes the Solr connection.
     *
     * @return void
     */
    protected function initializeSearch()
    {
        $connectionManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager');
        $solrConnection = $connectionManager->getConnectionByPageId($this->pageId);

        $this->search = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Search',
            $solrConnection);
    }

    /**
     * Index Action, provides an array of documents indexed for a given page.
     *
     * @param integer $pageId The current page's uid.
     * @return \stdClass
     */
    public function indexAction($pageId)
    {
        $this->initialize($pageId);

        $responseDocuments = array();
        $documents = $this->getIndexDocuments();

        foreach ($documents as $key => $document) {
            $responseDocuments[$key] = array(
                'id' => $document->id,
                'type' => $document->type,
                'title' => $document->title,
                '__data' => $this->formatDocumentData($document)
            );
        }

        $response = new \stdClass();
        $response->success = true;
        $response->metaData = $this->buildResponseMetaData();
        $response->numFound = $this->search->getNumberOfResults();
        $response->documents = $responseDocuments;

        return $response;
    }

    /**
     * Queries Solr for the current page's documents.
     *
     * @return array An array of Apache_Solr_Document objects
     */
    protected function getIndexDocuments()
    {
        /* @var Query $query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query',
            '');
        $query->setQueryType('standard');
        $query->useRawQueryString(true);
        $query->setQueryString('*:*');
        $query->addFilter('(type:pages AND uid:' . $this->pageId . ') OR (*:* AND pid:' . $this->pageId . ' NOT type:pages)');
        $query->addFilter('siteHash:' . Site::getSiteByPageId($this->pageId)->getSiteHash());
        $query->setFieldList('*');
        $query->setSorting('type asc, title asc');

        $this->search->search($query, 0, 10000);

        return $this->search->getResultDocumentsEscaped();
    }

    /**
     * Builds the response's meta data / description.
     *
     * @return object Response meta data
     */
    protected function buildResponseMetaData()
    {
        $metaData = new \stdClass();
        $metaData->idProperty = 'id';
        $metaData->root = 'documents';
        $metaData->totalProperty = 'numFound';
        $metaData->sortInfo = array(
            'field' => 'type',
            'direction' => 'ASC'
        );
        $metaData->fields = $this->buildResponseFieldDescription();

        return $metaData;
    }

    /**
     * Builds a description of the fields returned to ExtDirect calls.
     *
     * @return array An array of response field descriptions.
     */
    protected function buildResponseFieldDescription()
    {
        $fields = array();
        $fieldNames = array('id', 'type', 'title', '__data');

        foreach ($fieldNames as $fieldName) {
            $field = new \stdClass();
            $field->name = $fieldName;

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Formats a document's fields so that they can be iterated by an
     * ExtJs XTemplate.
     *
     * @param \Apache_Solr_Document $document The Solr document to format
     * @return array Formatted document field data, ready to be used in an ExtJs XTemplate iterator
     */
    protected function formatDocumentData(\Apache_Solr_Document $document)
    {
        $fields = array();
        foreach ($document as $fieldName => $value) {
            $fields[$fieldName] = $value;
        }
        ksort($fields);

        $sortedData = $fields;

        $formattedData = array();
        foreach ($sortedData as $fieldName => $fieldValue) {
            if (is_array($fieldValue)) {
                $formattedData[] = array(
                    'fieldName' => $fieldName,
                    'fieldValue' => '- array(' . count($fieldValue) . ') -'
                );
                $formattedData = $this->formatMultiValueFields($fieldValue, $formattedData);
            } else {
                $formattedData[] = array(
                    'fieldName' => $fieldName,
                    'fieldValue' => htmlspecialchars($fieldValue)
                );
            }
        }

        return $formattedData;
    }

    /**
     * Formats multi value fields
     *
     * @param array $multipleFieldValues
     * @param array $formattedData
     * @return array
     */
    protected function formatMultiValueFields(array $multipleFieldValues, array $formattedData)
    {
        // restrict to 10 elements
        $valueCount = count($multipleFieldValues);
        $multipleFieldValues = array_slice($multipleFieldValues, 0, 10);

        foreach ($multipleFieldValues as $index => $singleFieldValue) {
            if (is_array($singleFieldValue)) {
                $fieldValue = '- array(' . count($singleFieldValue) . ') -';
            } else {
                $fieldValue = htmlspecialchars($singleFieldValue);
            }

            $formattedData[] = array(
                'fieldName' => '',
                'fieldValue' => $fieldValue
            );
        }

        if ($valueCount > 10) {
            $formattedData[] = array(
                'fieldName' => '',
                'fieldValue' => '...'
            );
        }

        return $formattedData;
    }
}
