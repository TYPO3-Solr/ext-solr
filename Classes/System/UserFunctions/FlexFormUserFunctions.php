<?php
namespace ApacheSolrForTypo3\Solr\System\UserFunctions;

/*
 * Copyright (C) 2016  Daniel Siepmann <coding@daniel-siepmann.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 */

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class contains all user functions for flexforms.
 *
 * @author Daniel Siepmann <coding@daniel-siepmann.de>
 */
class FlexFormUserFunctions
{
    /**
     * Provides all facet fields for a flexform select, enabling the editor to select one of them.
     *
     * @param array $parentInformation
     *
     * @return void
     */
    public function getFacetFieldsFromSchema(array &$parentInformation)
    {
        $pageRecord = $parentInformation['flexParentDatabaseRow'];
        $configuredFacets = $this->getConfiguredFacetsForPage($pageRecord['pid']);

        if (!is_array($pageRecord)) {
            $parentInformation['items'] = [];
            return;
        }

        $newItems = $this->getParsedSolrFieldsFromSchema($configuredFacets, $pageRecord);
        $parentInformation['items'] = $newItems;
    }

    /**
     * This method parses the solr schema fields into the required format for the backend flexform.
     *
     * @param array $configuredFacets
     * @param array $pageRecord
     * @return mixed
     */
    protected function getParsedSolrFieldsFromSchema($configuredFacets, $pageRecord)
    {
        $newItems = [];

        array_map(function($fieldName) use (&$newItems, $configuredFacets) {
            $value = $fieldName;
            $label = $fieldName;

            $facetNameFilter = function($facet) use ($fieldName) {
                return ($facet['field'] === $fieldName);
            };
            $configuredFacets = array_filter($configuredFacets, $facetNameFilter);
            if (!empty($configuredFacets)) {
                $configuredFacet = array_values($configuredFacets);
                $label = $configuredFacet[0]['label'];
            }

            $newItems[$label] = [$label, $value];
        }, $this->getFieldNamesFromSolrMetaDataForPage($pageRecord));

        ksort($newItems, SORT_NATURAL);
        return $newItems;
    }
    /**
     * Retrieves the configured facets for a page.
     *
     * @param integer $pid
     * @return array
     */
    protected function getConfiguredFacetsForPage($pid)
    {
        $typoScriptConfiguration = Util::getSolrConfigurationFromPageId($pid);
        return $typoScriptConfiguration->getSearchFacetingFacets();
    }

    /**
     * Get solr connection.
     *
     * @param array $pageRecord
     *
     * @return \ApacheSolrForTypo3\Solr\SolrService
     */
    protected function getConnection(array $pageRecord)
    {
        return GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId($pageRecord['pid'], $pageRecord['sys_language_uid']);
    }

    /**
     * Retrieves all fieldnames that occure in the solr schema for one page.
     *
     * @param array $pageRecord
     * @return array
     */
    protected function getFieldNamesFromSolrMetaDataForPage(array $pageRecord)
    {
        return array_keys((array)$this->getConnection($pageRecord)->getFieldsMetaData());
    }
}
