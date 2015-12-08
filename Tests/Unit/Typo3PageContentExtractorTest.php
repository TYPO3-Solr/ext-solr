<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Tests the TYPO3 page content extractor
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Typo3PageContentExtractorTest extends UnitTest
{

    public function setUp()
    {
        $TSFE = $this->getDumbMock('\\TYPO3\\CMS\\Frontend\\Controller\\TypoScriptFrontendController');

        $GLOBALS['TSFE'] = $TSFE;
        /** @var $GLOBALS ['TSFE']->tmpl  \TYPO3\CMS\Core\TypoScript\TemplateService */
        $GLOBALS['TSFE']->tmpl = $this->getMock('\\TYPO3\\CMS\\Core\\TypoScript\\TemplateService', array('linkData'));
        $GLOBALS['TSFE']->tmpl->init();
        $GLOBALS['TSFE']->tmpl->getFileName_backPath = PATH_site;
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['queue.']['pages.']['excludeContentByClass'] = 'typo3-search-exclude';
    }


    /**
     * @test
     */
    public function changesNbspToSpace()
    {
        $content = '<!-- TYPO3SEARCH_begin -->In Olten&nbsp;ist<!-- TYPO3SEARCH_end -->';
        $expectedResult = 'In Olten ist';

        $contentExtractor = GeneralUtility::makeInstance(
            'ApacheSolrForTypo3\\Solr\\Typo3PageContentExtractor',
            $content
        );
        $actualResult = $contentExtractor->getIndexableContent();

        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function canExcludeContentByClass()
    {
        $content = '<!-- TYPO3SEARCH_begin --><div class="typo3-search-exclude">Exclude content</div><p>Expected content</p><!-- TYPO3SEARCH_end -->';
        $expectedResult = '<!-- TYPO3SEARCH_begin --><p>Expected content</p><!-- TYPO3SEARCH_end -->';

        $contentExtractor = GeneralUtility::makeInstance(
            'ApacheSolrForTypo3\\Solr\\Typo3PageContentExtractor',
            $content
        );

        $actualResult = $contentExtractor->excludeContentByClass($content);
        $this->assertEquals($expectedResult, $actualResult);
    }

}

