<?php

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

use ApacheSolrForTypo3\Solr\Controller\SearchController;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use ApacheSolrForTypo3\Solr\ViewHelpers\SearchFormViewHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3\CMS\Fluid\View\TemplatePaths;
use TYPO3Fluid\Fluid\Core\Cache\FluidCacheInterface;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentProcessorInterface;

class SearchFormViewHelperTest extends SetUpUnitTestCase
{
    protected MockObject|SearchFormViewHelper $viewHelper;
    protected MockObject|UriBuilder $uriBuilderMock;
    protected MockObject|TypoScriptConfiguration $typoScriptConfigurationMock;

    protected function setUp(): void
    {
        $this->uriBuilderMock = $this->createMock(UriBuilder::class);
        $this->typoScriptConfigurationMock = $this->createMock(TypoScriptConfiguration::class);

        $this->viewHelper = $this->getMockBuilder(SearchFormViewHelper::class)
            ->setConstructorArgs(
                [
                    'uriBuilder' => $this->uriBuilderMock,
                ],
            )
            ->onlyMethods([
                'getTypoScriptConfiguration',
                'getTemplateVariableContainer',
                'getSearchResultSet',
                'renderChildren',
            ])
            ->getMock();
        $renderingContext = new RenderingContext(
            $this->createMock(ViewHelperResolver::class),
            $this->createMock(FluidCacheInterface::class),
            [],
            [],
            new TemplatePaths(),
            $this->createMock(ArgumentProcessorInterface::class),
        );
        $request = new Request((new ServerRequest())->withAttribute('extbase', new ExtbaseRequestParameters(SearchController::class)));
        $renderingContext->setAttribute(ServerRequestInterface::class, $request);
        $this->viewHelper->setRenderingContext($renderingContext);
        $this->viewHelper->expects(self::any())->method('getTypoScriptConfiguration')->willReturn($this->typoScriptConfigurationMock);
        $this->viewHelper->expects(self::any())->method('getTemplateVariableContainer')->willReturn($this->createMock(VariableProviderInterface::class));
        $this->viewHelper->expects(self::once())->method('renderChildren')->willReturn('');
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

    #[Test]
    public function canSetTargetPageUidFromConfigurationWhenNullWasPassed(): void
    {
        $this->typoScriptConfigurationMock->expects(self::any())->method('getSearchTargetPage')->willReturn(888);
        $this->viewHelper->expects(self::once())->method('getSearchResultSet')->willReturn(null);
        $this->viewHelper->setArguments(['additionalParams' => [], 'argumentsToBeExcludedFromQueryString' => []]);

        $this->assertUriIsBuildForPageUid(888);
        $this->viewHelper->render();
    }

    #[Test]
    public function canUsePassedPageUidWhenNoTargetPageIsConfigured(): void
    {
        $this->typoScriptConfigurationMock->expects(self::any())->method('getSearchTargetPage')->willReturn(0);
        $this->viewHelper->expects(self::once())->method('getSearchResultSet')->willReturn(null);
        $this->viewHelper->setArguments(['pageUid' => 4711, 'additionalParams' => [], 'argumentsToBeExcludedFromQueryString' => []]);

        $this->assertUriIsBuildForPageUid(4711);
        $this->viewHelper->render();
    }

    #[Test]
    public function passedPageUidHasPriorityOverConfiguredTargetPageUid(): void
    {
        $this->typoScriptConfigurationMock->expects(self::any())->method('getSearchTargetPage')->willReturn(888);
        $this->viewHelper->expects(self::once())->method('getSearchResultSet')->willReturn(null);
        $this->viewHelper->setArguments(['pageUid' => 4711, 'additionalParams' => [], 'argumentsToBeExcludedFromQueryString' => []]);

        $this->assertUriIsBuildForPageUid(4711);
        $this->viewHelper->render();
    }
}
