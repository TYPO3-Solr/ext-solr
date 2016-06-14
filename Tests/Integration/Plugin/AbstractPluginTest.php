<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Plugin;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use DOMDocument;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageGenerator;

/**
 * Abstract base class for plugin integration tests.
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
 */
abstract class AbstractPluginTest extends IntegrationTest
{

    /**
     * @param array $importPageIds
     * @param string $fixture
     * @param string $plugin
     * @param integer $pluginPageUid
     * @return \ApacheSolrForTypo3\Solr\Plugin\Results\Results
     */
    protected function importTestDataSetAndGetInitializedPlugin($importPageIds, $fixture, $plugin = 'results', $pluginPageUid = 1)
    {
        $this->indexPageIdsFromFixture($fixture, $importPageIds);
        sleep(2);
        $plugin = $this->getPluginInstance($plugin, $pluginPageUid);
        return $plugin;
    }

    /**
     * @param $plugin
     * @param $pluginPageId
     * @return \ApacheSolrForTypo3\Solr\Plugin\Results\Results
     * @throws \InvalidArgumentException
     */
    protected function getPluginInstance($plugin, $pluginPageId = 1)
    {
        switch ($plugin) {
            case 'results':
                $pluginClassName = 'ApacheSolrForTypo3\Solr\Plugin\Results\Results';
                break;
            case 'search':
                $pluginClassName = 'ApacheSolrForTypo3\Solr\Plugin\Search\Search';
                break;
            case 'frequent_search':
                $pluginClassName = 'ApacheSolrForTypo3\Solr\Plugin\FrequentSearches\FrequentSearches';
                break;
            default:
                throw new \InvalidArgumentException("Invalid plugin " . $plugin);

        }


        $fakeTSFE = $this->getConfiguredTSFE(array(), $pluginPageId);
        $fakeTSFE->newCObj();
        $GLOBALS['TSFE'] = $fakeTSFE;

        PageGenerator::pagegenInit();
        PageGenerator::renderContent();

        $fakeTSFE->newCObj();
        $fakeTSFE->fe_user->id = 'id';

        /** @var $plugin \ApacheSolrForTypo3\Solr\Plugin\Results\Results */
        $plugin = GeneralUtility::makeInstance($pluginClassName);
        $plugin->cObj = $fakeTSFE->cObj;
        return $plugin;
    }


    /**
     * @param string $content
     * @param string $id
     * @return string
     */
    protected function getIdContent($content, $id)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . $content);

        return $dom->saveXML($dom->getElementById($id));
    }

    /**
     * Assert that a docContainer with a specific id contains an expected content snipped.
     *
     * @param string $expectedToContain
     * @param string $content
     * @param $id
     */
    protected function assertContainerByIdContains($expectedToContain, $content, $id)
    {
        $containerContent = $this->getIdContent($content, $id);
        $this->assertContains($expectedToContain, $containerContent, 'Failed asserting that container with id ' . $id .' contains ' . $expectedToContain);
    }
}
