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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\ViewHelpers;

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3Fluid\Fluid\View\TemplateView;

class SearchFormViewHelperTest extends IntegrationTestBase
{
    protected RenderingContextFactory $renderingContextFactory;
    protected MockObject|TypoScriptConfiguration $typoScriptConfigurationMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        $this->writeSiteConfiguration(
            'test',
            $this->buildSiteConfiguration(1, '/'),
        );

        $this->renderingContextFactory = $this->get(RenderingContextFactory::class);
        $this->typoScriptConfigurationMock = $this->createMock(TypoScriptConfiguration::class);
    }

    #[Test]
    public function queryArgumentWillBePrefixedWithPluginNamespace(): void
    {
        $templateSource = '
            <html xmlns="http://www.w3.org/1999/xhtml" lang="en"
                  xmlns:f="http://typo3.org/ns/TYPO3/Fluid/ViewHelpers"
                  xmlns:s="http://typo3.org/ns/ApacheSolrForTypo3/Solr/ViewHelpers"
                  data-namespace-typo3-fluid="true">

                <s:searchForm>
                    <input type="text" name="{pluginNamespace}[q]" value="{q}" maxlength="50" />
                    <button class="btn btn-primary tx-solr-submit" type="submit">Search</button>
                </s:searchForm>
            </html>
        ';

        $renderingContext = $this->getRenderingContext();
        $renderingContext->getTemplatePaths()->setTemplateSource($templateSource);
        $renderingContext->getVariableProvider()->add('typoScriptConfiguration', $this->typoScriptConfigurationMock);
        $renderingContext->getVariableProvider()->add('pluginNamespace', 'tx_solr');

        $view = new TemplateView($renderingContext);
        $output = $view->render();

        self::assertStringContainsString('tx_solr[q]', $output);
    }

    public static function methodDataProvider(): array
    {
        return [
            'Method "GET"' => ['get', 'method="get"'],
            'Method "POST"' => ['post', 'method="post"'],
        ];
    }

    #[Test]
    #[DataProvider(
        methodName: 'methodDataProvider',
    )]
    public function methodArgumentWillBePassedToFormTag(string $method, string $expectedString): void
    {
        $templateSource = sprintf('
            <html xmlns="http://www.w3.org/1999/xhtml" lang="en"
                  xmlns:f="http://typo3.org/ns/TYPO3/Fluid/ViewHelpers"
                  xmlns:s="http://typo3.org/ns/ApacheSolrForTypo3/Solr/ViewHelpers"
                  data-namespace-typo3-fluid="true">

                <s:searchForm method="%s">
                    <input type="text" name="{pluginNamespace}[q]" value="{q}" maxlength="50" />
                    <button class="btn btn-primary tx-solr-submit" type="submit">Search</button>
                </s:searchForm>
            </html>
        ', $method);

        $renderingContext = $this->getRenderingContext();
        $renderingContext->getTemplatePaths()->setTemplateSource($templateSource);
        $renderingContext->getVariableProvider()->add('typoScriptConfiguration', $this->typoScriptConfigurationMock);

        $view = new TemplateView($renderingContext);
        $output = $view->render();

        self::assertStringContainsString($expectedString, $output);
    }

    public static function pageUidDataProvider(): array
    {
        return [
            'Non given Page UID' => ['', 0, 'action="/"'],
            'Non given Page UID, but with search target page UID 3' => ['', 3, 'action="/dummy-1-2/dummy-1-2-3"'],
            'Page UID 5' => ['pageUid="5"', 0, 'action="/dummy-1-5"'],
            'Page UID 2, but with search target page UID 7' => ['pageUid="2"', 7, 'action="/dummy-1-2"'],
        ];
    }

    #[Test]
    #[DataProvider(
        methodName: 'pageUidDataProvider',
    )]
    public function pageUidArgumentWillBeUsedToBuildActionUri(
        string $pageUidArgument,
        int $searchTargetPageUid,
        string $expectedString,
    ): void {
        $templateSource = sprintf('
            <html xmlns="http://www.w3.org/1999/xhtml" lang="en"
                  xmlns:f="http://typo3.org/ns/TYPO3/Fluid/ViewHelpers"
                  xmlns:s="http://typo3.org/ns/ApacheSolrForTypo3/Solr/ViewHelpers"
                  data-namespace-typo3-fluid="true">

                <s:searchForm %s>
                    <input type="text" name="{pluginNamespace}[q]" value="{q}" maxlength="50" />
                    <button class="btn btn-primary tx-solr-submit" type="submit">Search</button>
                </s:searchForm>
            </html>
        ', $pageUidArgument);

        $this->typoScriptConfigurationMock
            ->expects(self::any())
            ->method('getSearchTargetPage')
            ->willReturn($searchTargetPageUid);

        $renderingContext = $this->getRenderingContext();
        $renderingContext->getTemplatePaths()->setTemplateSource($templateSource);
        $renderingContext->getVariableProvider()->add('typoScriptConfiguration', $this->typoScriptConfigurationMock);

        $view = new TemplateView($renderingContext);
        $output = $view->render();

        self::assertStringContainsString($expectedString, $output);
    }

    #[Test]
    public function changeSuggestPageType(): void
    {
        $templateSource = '
            <html xmlns="http://www.w3.org/1999/xhtml" lang="en"
                  xmlns:f="http://typo3.org/ns/TYPO3/Fluid/ViewHelpers"
                  xmlns:s="http://typo3.org/ns/ApacheSolrForTypo3/Solr/ViewHelpers"
                  data-namespace-typo3-fluid="true">

                <s:searchForm additionalFilters="{foo:bar}" suggestPageType="1234">
                    <input type="text" name="{pluginNamespace}[q]" value="{q}" maxlength="50" />
                    <button class="btn btn-primary tx-solr-submit" type="submit">Search</button>
                </s:searchForm>
            </html>
        ';

        $renderingContext = $this->getRenderingContext();
        $renderingContext->getTemplatePaths()->setTemplateSource($templateSource);
        $renderingContext->getVariableProvider()->add('typoScriptConfiguration', $this->typoScriptConfigurationMock);

        $view = new TemplateView($renderingContext);
        $output = $view->render();

        self::assertStringContainsString('data-suggest="/?type=1234"', $output);
    }

    #[Test]
    public function additionalFiltersArgumentWillBeAddedToSolrForm(): void
    {
        $templateSource = '
            <html xmlns="http://www.w3.org/1999/xhtml" lang="en"
                  xmlns:f="http://typo3.org/ns/TYPO3/Fluid/ViewHelpers"
                  xmlns:s="http://typo3.org/ns/ApacheSolrForTypo3/Solr/ViewHelpers"
                  data-namespace-typo3-fluid="true">

                <s:searchForm additionalFilters="{foo:bar}">
                    <input type="text" name="{pluginNamespace}[q]" value="{q}" maxlength="50" />
                    <button class="btn btn-primary tx-solr-submit" type="submit">Search</button>
                </s:searchForm>
            </html>
        ';

        $renderingContext = $this->getRenderingContext();
        $renderingContext->getTemplatePaths()->setTemplateSource($templateSource);
        $renderingContext->getVariableProvider()->add('typoScriptConfiguration', $this->typoScriptConfigurationMock);

        $view = new TemplateView($renderingContext);
        $output = $view->render();

        self::assertStringContainsString('data-suggest="/?type=7384"', $output);
    }

    #[Test]
    public function additionalFiltersArgumentWillNotBeAddedToSolrForm(): void
    {
        $templateSource = '
            <html xmlns="http://www.w3.org/1999/xhtml" lang="en"
                  xmlns:f="http://typo3.org/ns/TYPO3/Fluid/ViewHelpers"
                  xmlns:s="http://typo3.org/ns/ApacheSolrForTypo3/Solr/ViewHelpers"
                  data-namespace-typo3-fluid="true">

                <s:searchForm additionalFilters="{foo:bar}" addSuggestUrl="false">
                    <input type="text" name="{pluginNamespace}[q]" value="{q}" maxlength="50" />
                    <button class="btn btn-primary tx-solr-submit" type="submit">Search</button>
                </s:searchForm>
            </html>
        ';

        $renderingContext = $this->getRenderingContext();
        $renderingContext->getTemplatePaths()->setTemplateSource($templateSource);
        $renderingContext->getVariableProvider()->add('typoScriptConfiguration', $this->typoScriptConfigurationMock);

        $view = new TemplateView($renderingContext);
        $output = $view->render();

        self::assertStringContainsString('data-suggest', $output);
    }

    #[Test]
    public function dataSuggestHeaderWillBeAddedToSolrForm(): void
    {
        $templateSource = '
            <html xmlns="http://www.w3.org/1999/xhtml" lang="en"
                  xmlns:f="http://typo3.org/ns/TYPO3/Fluid/ViewHelpers"
                  xmlns:s="http://typo3.org/ns/ApacheSolrForTypo3/Solr/ViewHelpers"
                  data-namespace-typo3-fluid="true">

                <s:searchForm additionalFilters="{foo:bar}" suggestHeader="foo=bar">
                    <input type="text" name="{pluginNamespace}[q]" value="{q}" maxlength="50" />
                    <button class="btn btn-primary tx-solr-submit" type="submit">Search</button>
                </s:searchForm>
            </html>
        ';

        $renderingContext = $this->getRenderingContext();
        $renderingContext->getTemplatePaths()->setTemplateSource($templateSource);
        $renderingContext->getVariableProvider()->add('typoScriptConfiguration', $this->typoScriptConfigurationMock);

        $view = new TemplateView($renderingContext);
        $output = $view->render();

        self::assertStringContainsString('data-suggest-header="foo=bar"', $output);
    }

    private function getRenderingContext(): RenderingContext
    {
        $frontendTypoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTypoScript->setSetupArray([]);
        $frontendTypoScript->setConfigArray([]);

        $extbaseRequestParameters = new ExtbaseRequestParameters();
        $extbaseRequestParameters->setControllerExtensionName('Examples');
        $extbaseRequestParameters->setControllerName('Detail');
        $extbaseRequestParameters->setControllerActionName('show');
        $extbaseRequestParameters->setPluginName('Haiku');

        $contentObjectRenderer = $this->get(ContentObjectRenderer::class);

        $request = new ServerRequest();
        $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        $request = $request->withAttribute('routing', new PageArguments(1, '0', ['untrusted' => 123]));
        $request = $request->withAttribute('extbase', $extbaseRequestParameters);
        $request = $request->withAttribute('currentContentObject', $contentObjectRenderer);
        $request = $request->withAttribute('frontend.typoscript', $frontendTypoScript);

        $contentObjectRenderer->setRequest($request);

        $configurationManager = $this->get(ConfigurationManagerInterface::class);
        $configurationManager->setRequest($request);

        $pageInformation = new PageInformation();
        $pageInformation->setId(1);

        $request = $request->withAttribute('frontend.page.information', $pageInformation);
        $request = new Request($request);

        return $this->get(RenderingContextFactory::class)->create([], $request);
    }
}
