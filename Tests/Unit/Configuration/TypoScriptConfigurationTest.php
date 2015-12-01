<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Configuration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2016 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the configuration object can be used as expected
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class TypoScriptConfigurationTest extends UnitTest
{

    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * @return void
     */
    public function setUp()
    {
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['index.']['queue.']['tt_news.']['fields.']['content'] = 'SOLR_CONTENT';
        $fakeConfigurationArray['index.']['queue.']['tt_news.']['fields.']['content.']['field'] = 'bodytext';
        $this->configuration = new TypoScriptConfiguration($fakeConfigurationArray);
    }

    /**
     * @test
     */
    public function canGetValueByPath()
    {
        $testPath = 'plugin.tx_solr.index.queue.tt_news.fields.content';
        $this->assertSame('SOLR_CONTENT', $this->configuration->getValueByPath($testPath), 'Could not get configuration value by path');
    }

    /**
     * @test
     */
    public function canGetObjectByPath()
    {
        $testPath = 'plugin.tx_solr.index.queue.tt_news.fields.content';
        $expectedResult = array(
            'content' => 'SOLR_CONTENT',
            'content.' => array('field' => 'bodytext')
        );

        $this->assertSame($expectedResult, $this->configuration->getObjectByPath($testPath), 'Could not get configuration object by path');
    }

    /**
     * @test
     */
    public function canUseAsArrayForBackwardsCompatibility()
    {
        $value = $this->configuration['index.']['queue.']['tt_news.']['fields.']['content'];
        $this->assertSame($value, 'SOLR_CONTENT', 'Can not use the configuration object with array access as backwards compatible implementation');
    }
}
