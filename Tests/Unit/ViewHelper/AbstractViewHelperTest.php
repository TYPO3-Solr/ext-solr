<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Markus Friedrich <markus.friedrich@dkd.de>
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

use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract PHP Unit test for view helpers
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
abstract class AbstractViewHelperTest extends UnitTest
{
    /**
     * Configuration manager
     *
     * @var \ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager $configurationManager
     */
    protected $configurationManager;

    /**
     * Set up the view helper test
     *
     * @return void
     */
    public function setUp()
    {
        $TSFE = $this->getDumbMock('\\TYPO3\\CMS\\Frontend\\Controller\\TypoScriptFrontendController');
        $GLOBALS['TSFE'] = $TSFE;

        /** @var $GLOBALS ['TSFE']->tmpl  \TYPO3\CMS\Core\TypoScript\TemplateService */
        $GLOBALS['TSFE']->tmpl = $this->getDumbMock('\\TYPO3\\CMS\\Core\\TypoScript\\TemplateService', array('linkData'));
        $GLOBALS['TSFE']->tmpl->init();
        $GLOBALS['TSFE']->tmpl->getFileName_backPath = PATH_site;
        $GLOBALS['TSFE']->tmpl->setup['config.']['typolinkEnableLinksAcrossDomains'] = 0;
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['targetPage'] = 25;
        $GLOBALS['TSFE']->tmpl->setup['config.']['tx_realurl_enable'] = '0';
        $GLOBALS['TSFE']->config['config'] = $this->tmpl->setup['config.'];

        /** @var \ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager $configurationManager */
        $this->configurationManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\System\\Configuration\\ConfigurationManager');
    }
}
