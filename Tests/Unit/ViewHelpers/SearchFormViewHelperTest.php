<?php

/** @noinspection PhpUnhandledExceptionInspection */

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelpers;

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\ViewHelpers\SearchFormViewHelper;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SearchFormViewHelperTest extends UnitTest
{
    protected MockObject|SearchFormViewHelper $viewHelper;
    protected MockObject|UriBuilder $uriBuilderMock;
    protected MockObject|TypoScriptConfiguration $typoScriptConfigurationMock;

    protected function setUp(): void
    {
        $this->uriBuilderMock = $this->getDumbMock(UriBuilder::class);
        $this->typoScriptConfigurationMock = $this->getDumbMock(TypoScriptConfiguration::class);

        $this->viewHelper = $this->getMockBuilder(SearchFormViewHelper::class)
            ->setConstructorArgs(
                [
                    'uriBuilder' => $this->uriBuilderMock,
                ]
            )
            ->onlyMethods([
                'getControllerContext',
                'getTypoScriptConfiguration',
                'getTemplateVariableContainer',
                'getSearchResultSet',
                'renderChildren',
                'getIsSiteManagedSite',
            ])
            ->getMock();
        $this->viewHelper->expects(self::any())->method('getTypoScriptConfiguration')->willReturn($this->typoScriptConfigurationMock);
        $this->viewHelper->expects(self::any())->method('getTemplateVariableContainer')->willReturn($this->getDumbMock(VariableProviderInterface::class));
        $this->viewHelper->expects(self::once())->method('renderChildren')->willReturn('');
        $this->viewHelper->expects(self::once())->method('getIsSiteManagedSite')->willReturn(false);
        parent::setUp();
    }

    /**
     * @param int $pageUid
     */
    protected function assertUriIsBuildForPageUid(int $pageUid)
    {
        $this->uriBuilderMock->expects(self::any())->method('reset')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setTargetPageUid')->with($pageUid)->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setTargetPageType')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setNoCache')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setArguments')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setCreateAbsoluteUri')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setAddQueryString')->willReturn($this->uriBuilderMock);
        $this->uriBuilderMock->expects(self::once())->method('setArgumentsToBeExcludedFromQueryString')->willReturn($this->uriBuilderMock);
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
