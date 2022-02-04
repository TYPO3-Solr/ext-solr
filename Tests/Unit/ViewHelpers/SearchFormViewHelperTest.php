<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelpers\Facet\Options\Group\Prefix;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\ViewHelpers\SearchFormViewHelper;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SearchFormViewHelperTest extends UnitTest
{
    /**
     * @var SearchFormViewHelper
     */
    protected $viewHelper;

    /**
     * @var UriBuilder
     */
    protected $uriBuilderMock;

    /**
     * @var TypoScriptConfiguration
     */
    protected $typoScriptConfigurationMock;

    protected function setUp(): void
    {
        $this->uriBuilderMock = $this->getDumbMock(UriBuilder::class);
        $this->typoScriptConfigurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $controllerContextMock = $this->getDumbMock(ControllerContext::class);
        $controllerContextMock->expects(self::once())->method('getUriBuilder')->willReturn($this->uriBuilderMock);

        $this->viewHelper = $this->getMockBuilder(SearchFormViewHelper::class)->onlyMethods(['getControllerContext', 'getTypoScriptConfiguration', 'getTemplateVariableContainer', 'getSearchResultSet', 'renderChildren', 'getIsSiteManagedSite'])->getMock();
        $this->viewHelper->expects(self::any())->method('getControllerContext')->willReturn($controllerContextMock);
        $this->viewHelper->expects(self::any())->method('getTypoScriptConfiguration')->willReturn($this->typoScriptConfigurationMock);
        $this->viewHelper->expects(self::any())->method('getTemplateVariableContainer')->willReturn($this->getDumbMock(VariableProviderInterface::class));
        $this->viewHelper->expects(self::once())->method('renderChildren')->willReturn('');
        $this->viewHelper->expects(self::once())->method('getIsSiteManagedSite')->willReturn(false);
        parent::setUp();
    }

    /**
     * @param int $pageUid
     */
    protected function assertUriIsBuildForPageUid($pageUid)
    {
        $this->uriBuilderMock->expects(self::any())->method('reset')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setTargetPageUid')->with($pageUid)->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setTargetPageType')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setNoCache')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setArguments')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setCreateAbsoluteUri')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setAddQueryString')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setArgumentsToBeExcludedFromQueryString')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setAddQueryStringMethod')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setAddQueryString')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setSection')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('build')->willReturn('index.php?id=' . $pageUid);
    }

    /**
     * @test
     */
    public function canSetTargetPageUidFromConfigurationWhenNullWasPassed()
    {
        $this->typoScriptConfigurationMock->expects(self::any())->method('getSearchTargetPage')->willReturn(888);
        $this->viewHelper->expects(self::once())->method('getSearchResultSet')->willReturn(null);
        $this->viewHelper->setArguments(['additionalParams' => [], 'argumentsToBeExcludedFromQueryString' => []]);

        $this->assertUriIsBuildForPageUid(888);
        $this->viewHelper->render();
    }

    /**
     * @test
     */
    public function canUsePassedPageUidWhenNoTargetPageIsConfigured()
    {
        $this->typoScriptConfigurationMock->expects(self::any())->method('getSearchTargetPage')->willReturn(0);
        $this->viewHelper->expects(self::once())->method('getSearchResultSet')->willReturn(null);
        $this->viewHelper->setArguments(['pageUid' => 4711, 'additionalParams' => [], 'argumentsToBeExcludedFromQueryString' => []]);

        $this->assertUriIsBuildForPageUid(4711);
        $this->viewHelper->render();
    }

    /**
     * @test
     */
    public function passedPageUidHasPriorityOverConfiguredTargetPageUid()
    {
        $this->typoScriptConfigurationMock->expects(self::any())->method('getSearchTargetPage')->willReturn(888);
        $this->viewHelper->expects(self::once())->method('getSearchResultSet')->willReturn(null);
        $this->viewHelper->setArguments(['pageUid' => 4711, 'additionalParams' => [], 'argumentsToBeExcludedFromQueryString' => []]);

        $this->assertUriIsBuildForPageUid(4711);
        $this->viewHelper->render();
    }
}
