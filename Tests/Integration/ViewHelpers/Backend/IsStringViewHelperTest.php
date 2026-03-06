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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\ViewHelpers\Backend;

use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Testcase for the IsStringViewHelper
 */
class IsStringViewHelperTest extends IntegrationTestBase
{
    protected RenderingContextFactory $renderingContextFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderingContextFactory = $this->get(RenderingContextFactory::class);
    }

    public static function stringDataProvider(): array
    {
        return [
            'Test with empty string' => ['', 'Is string'],
            'Test with string' => ['TYPO3', 'Is string'],
            'Test with "false" string' => ['false', 'Is string'],
            'Test with "true" string' => ['true', 'Is string'],
            'Test with "zero" string' => ['zero', 'Is string'],
            'Test with empty array' => ['{}', 'Is not string'],
            'Test with filled array' => ['{foo: \'bar\'}', 'Is not string'],
            'Test with zero' => [0, 'Is not string'],
            'Test with integer' => [12, 'Is not string'],
        ];
    }

    #[Test]
    #[DataProvider(
        methodName: 'stringDataProvider',
    )]
    public function isStringRendersThenOrElse(string|int|array $value, string $expectedString): void
    {
        $templateSource = sprintf('
            <html xmlns="http://www.w3.org/1999/xhtml" lang="en"
                  xmlns:f="http://typo3.org/ns/TYPO3/Fluid/ViewHelpers"
                  xmlns:s="http://typo3.org/ns/ApacheSolrForTypo3/Solr/ViewHelpers"
                  data-namespace-typo3-fluid="true">

                <s:backend.isString value="%s">
                    <f:then>Is string1</f:then>
                    <f:else>Is not string</f:else>
                </s:backend.isString>
            </html>
        ', $value);

        $request = new ServerRequest();
        $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        $request = $request->withAttribute('extbase', new ExtbaseRequestParameters());

        $request = new Request($request);

        $renderingContext = $this->get(RenderingContextFactory::class)->create([], $request);
        $renderingContext->getTemplatePaths()->setTemplateSource($templateSource);

        $view = new TemplateView($renderingContext);
        $output = $view->render();

        self::assertStringContainsString($expectedString, $output);
    }
}
