<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Timo Schmidt <timo.schmidt@dkd.de>
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
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Tests the ApacheSolrForTypo3\Solr\Query\Modifier\Faceting class
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class FacetingTest extends UnitTest
{
    /**
     * @param $fakeConfigurationArray
     * @return array
     */
    private function getQueryParametersFromExecutedFacetingModifier($fakeConfigurationArray)
    {
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);
        /** @var $facetModifier \ApacheSolrForTypo3\Solr\Query\Modifier\Faceting */
        $facetModifier = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query\\Modifier\\Faceting', $fakeConfiguration);
        $facetModifier->modifyQuery($query);

        $queryParameter = $query->getQueryParameters();
        return $queryParameter;
    }

    /**
     * Checks if the faceting modifier can add a simple facet on the field type.
     *
     *  facets {
     *     type {
     *        field = type
     *     }
     *  }
     *
     * @test
     */
    public function testCanAddASimpleFacet()
    {
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = array(
            'type.' => array(
                'field' => 'type'
            )
        );
        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfigurationArray);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');
        $this->assertContains('type',  $queryParameter['facet.field'][0], 'Query string did not contain expected snipped');
    }

    /**
     * Checks if the faceting modifier can add a simple facet with a sortBy property with the value index.
     *
     *  facets {
     *     type {
     *        field = type
     *        sortBy = index
     *     }
     *  }
     *
     * @test
     */
    public function testCanAddSortByQueryArgument()
    {
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = array(
            'type.' => array(
                'field' => 'type',
                'sortBy' => 'index'
            )
        );
        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfigurationArray);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');
        $this->assertContains('lex',  $queryParameter['f.type.facet.sort'], 'Query string did not contain expected snipped');
    }

    /**
     * Checks when keepAllOptionsOnSelection is configured globally that {!ex=type,color} will be added
     * to the query.
     *
     * faceting {
     *    keepAllFacetsOnSelection = 1
     *    facets {
     *       type {
     *          field = type
     *       }
     *       color {
     *          field = color
     *       }
     *    }
     * }
     *
     * @test
     */
    public function testCanHandleKeepAllFacetsOnSelectionOnAllFacetWhenGloballyConfigured()
    {
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['keepAllFacetsOnSelection'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = array(
            'type.' => array(
                'field' => 'type',
            ),
            'color.' => array(
                'field' => 'color',
            )
        );
        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfigurationArray);

        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');
        $this->assertEquals('{!ex=type,color}type',  $queryParameter['facet.field'][0], 'Query string did not contain expected snipped');
        $this->assertEquals('{!ex=type,color}color',  $queryParameter['facet.field'][1], 'Query string did not contain expected snipped');
    }

    /**
     * Checks when keepAllOptionsOnSelection is configured globally that {!ex=type,color} will be added
     * to the query.
     *
     * faceting {
     *    facets {
     *       type {
     *          field = type
     *          keepAllOptionsOnSelection = 1
     *       }
     *       color {
     *          field = color
     *       }
     *    }
     * }
     *
     * @test
     */
    public function testCanHandleKeepAllOptionsOnSelectionForASingleFacet()
    {
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = array(
            'type.' => array(
                'field' => 'type',
                'keepAllOptionsOnSelection' => 1
            ),
            'color.' => array(
                'field' => 'color',
            )
        );

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfigurationArray);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');
        $this->assertEquals('{!ex=type}type',  $queryParameter['facet.field'][0], 'Query string did not contain expected snipped');
        $this->assertEquals('color',  $queryParameter['facet.field'][1], 'Query string did not contain expected snipped');
    }

    /**
     *
     * @test
     */
    public function testCanAddQueryFilters()
    {
        $fakeRequest = array(
            'tx_solr' => array('filter' => array(urlencode('color:red'),urlencode('type:product')))
        );

        $_GET = $fakeRequest;

        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = array(
            'type.' => array(
                'field' => 'type',
            ),
            'color.' => array(
                'field' => 'color',
            )
        );
        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfigurationArray);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');

        //do we have a filter query for both present?
        $this->assertEquals('(color:"red")', $queryParameter['fq'][0], 'Did not build filter query from color');
        $this->assertEquals('(type:"product")', $queryParameter['fq'][1], 'Did not build filter query from type');
    }

    /**
     * @test
     */
    public function testCanAddQueryFiltersWithKeepAllOptionsOnSelectionFacet()
    {
        $fakeRequest = array(
            'tx_solr' => array('filter' => array(urlencode('color:red'),urlencode('type:product')))
        );

        $_GET = $fakeRequest;

        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = array(
            'type.' => array(
                'field' => 'type',
                'keepAllOptionsOnSelection' => 1
            ),
            'color.' => array(
                'field' => 'color',
            )
        );
        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfigurationArray);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');

        //do we have a filter query for both present?
        $this->assertEquals('(color:"red")', $queryParameter['fq'][0], 'Did not build filter query from color');
        $this->assertEquals('{!tag=type}(type:"product")', $queryParameter['fq'][1], 'Did not build filter query from type');
    }

    /**
     * @test
     */
    public function testCanAddQueryFiltersWithGlobalKeepAllOptionsOnSelection()
    {
        $fakeRequest = array(
            'tx_solr' => array('filter' => array(urlencode('color:red'),urlencode('type:product')))
        );

        $_GET = $fakeRequest;

        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['keepAllFacetsOnSelection'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = array(
            'type.' => array(
                'field' => 'type',
            ),
            'color.' => array(
                'field' => 'color',
            )
        );
        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfigurationArray);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');

        //do we have a filter query for both present?
        $this->assertEquals('{!tag=color}(color:"red")', $queryParameter['fq'][0], 'Did not build filter query from color');
        $this->assertEquals('{!tag=type}(type:"product")', $queryParameter['fq'][1], 'Did not build filter query from type');
    }
}
