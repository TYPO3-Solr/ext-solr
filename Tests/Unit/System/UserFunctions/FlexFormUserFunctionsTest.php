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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Service;

use ApacheSolrForTypo3\Solr\System\UserFunctions\FlexFormUserFunctions;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class FlexFormUserFunctionsTest extends UnitTest
{
    /**
     * @test
     */
    public function whenNoFacetsAreConfiguredAllSolrFieldsShouldBeAvailableAsFilter()
    {
        /** @var FlexFormUserFunctions $userFunc */
        $userFunc = $this->getMockBuilder(FlexFormUserFunctions::class)
            ->onlyMethods(['getFieldNamesFromSolrMetaDataForPage', 'getConfiguredFacetsForPage'])->getMock();

        $userFunc->expects(self::once())->method('getFieldNamesFromSolrMetaDataForPage')->willReturn(['type', 'pid', 'uid']);
        $userFunc->expects(self::once())->method('getConfiguredFacetsForPage')->willReturn([]);

        $parentInformation = [
            'flexParentDatabaseRow' => [
                'pid' => 4711,
            ],
        ];

        $userFunc->getFacetFieldsFromSchema($parentInformation);
        self::assertCount(3, $parentInformation['items']);
        self::assertEquals(0, $parentInformation['items'][0][0] ?? null);
    }

    /**
     * @test
     */
    public function labelIsUsedFromFacetWhenTheFacetIsConfiguredInTypoScript()
    {
        /** @var FlexFormUserFunctions $userFunc */
        $userFunc = $this->getMockBuilder(FlexFormUserFunctions::class)
            ->onlyMethods(['getFieldNamesFromSolrMetaDataForPage', 'getConfiguredFacetsForPage'])->getMock();

        $userFunc->expects(self::once())->method('getFieldNamesFromSolrMetaDataForPage')->willReturn(['type', 'pid', 'uid']);
        $userFunc->expects(self::once())->method('getConfiguredFacetsForPage')->willReturn([
            'myType.' => [
                'field' => 'type',
                'label' => 'The type',
            ],
        ]);

        $parentInformation = [
            'flexParentDatabaseRow' => [
                'pid' => 4711,
            ],
        ];

        $userFunc->getFacetFieldsFromSchema($parentInformation);
        self::assertCount(3, $parentInformation['items']);
        self::assertEquals('type (Facet Label: "The type")', $parentInformation['items']['type'][0]);
    }

    /**
     * @test
     */
    public function duplicateFacetLabelDoesNotMakeFieldsDisappearingInFlexForms()
    {
        /** @var FlexFormUserFunctions $flexFormUserFunctionsMock */
        $flexFormUserFunctionsMock = $this->getMockBuilder(FlexFormUserFunctions::class)
            ->onlyMethods(['getFieldNamesFromSolrMetaDataForPage', 'getConfiguredFacetsForPage'])->getMock();
        $flexFormUserFunctionsMock->expects(self::once())->method('getFieldNamesFromSolrMetaDataForPage')
            ->willReturn(
                ['some_field', 'someOther_field']
            );

        $flexFormUserFunctionsMock->expects(self::once())->method('getConfiguredFacetsForPage')
            ->willReturn([
                'someFacet.' => [
                    'field' => 'some_field',
                    'label' => 'TEXT',
                ],
                'someOtherFacet.' => [
                    'field' => 'someOther_field',
                    'label' => 'TEXT',
                ],
            ]);

        $parentInformation = [
            'flexParentDatabaseRow' => [
                'pid' => 4711,
            ],
        ];
        $flexFormUserFunctionsMock->getFacetFieldsFromSchema($parentInformation);
        self::assertCount(2, $parentInformation['items']);
    }

    /**
     * @test
     */
    public function facetLabelIsShownTranslatedInBracketsSignsInFlexFormsIfTranslationIsAvailable()
    {
        /** @var FlexFormUserFunctions $flexFormUserFunctionsMock */
        $flexFormUserFunctionsMock = $this->getMockBuilder(FlexFormUserFunctions::class)
            ->onlyMethods(['getFieldNamesFromSolrMetaDataForPage', 'getConfiguredFacetsForPage', 'getTranslation'])->getMock();
        $flexFormUserFunctionsMock->expects(self::once())->method('getFieldNamesFromSolrMetaDataForPage')
            ->willReturn(['some_field', 'someOther_field', 'someQuiteOther_field', 'uid', 'pid']);

        $flexFormUserFunctionsMock->expects(self::once())->method('getConfiguredFacetsForPage')
            ->willReturn([
                'someFacet.' => [
                    'field' => 'some_field',
                    'label' => 'LLL:EXT:some_ext/locallang.xlf:existing_label',
                ],
                'someOtherFacet.' => [
                    'field' => 'someOther_field',
                    'label' => 'LLL:EXT:some_ext/locallang.xlf:not_existing_label',
                ],
                'someQuiteOtherFacet.' => [
                    'field' => 'someQuiteOther_field',
                    'label' => 'LLL:EXT:some_ext/locallang.xlf:not_existing_label',
                ],
            ]);

        $flexFormUserFunctionsMock->expects(self::any())->method('getTranslation')->willReturnCallback(
            function () {
                $args = func_get_args();
                if ($args[0] === 'LLL:EXT:some_ext/locallang.xlf:existing_label') {
                    return 'Translated Facet';
                }
                return '';
            }
        );

        $parentInformation = [
            'flexParentDatabaseRow' => [
                'pid' => 4711,
            ],
        ];
        $flexFormUserFunctionsMock->getFacetFieldsFromSchema($parentInformation);

        self::assertCount(5, $parentInformation['items']);
        self::assertEquals('some_field (Facet Label: "Translated Facet")', $parentInformation['items']['some_field'][0]);
        self::assertEquals('someOther_field (Facet Label: "LLL:EXT:some_ext/locallang.xlf:not_existing_label")', $parentInformation['items']['someOther_field'][0]);
        self::assertEquals('someQuiteOther_field (Facet Label: "LLL:EXT:some_ext/locallang.xlf:not_existing_label")', $parentInformation['items']['someQuiteOther_field'][0]);
    }

    /**
     * @test
     */
    public function cObjectPathIsShownInBracketsSignsInFlexFormsIfcObjectIsUsed()
    {
        /** @var FlexFormUserFunctions $flexFormUserFunctionsMock */
        $flexFormUserFunctionsMock = $this->getMockBuilder(FlexFormUserFunctions::class)
            ->onlyMethods(['getFieldNamesFromSolrMetaDataForPage', 'getConfiguredFacetsForPage'])->getMock();
        $flexFormUserFunctionsMock->expects(self::once())->method('getFieldNamesFromSolrMetaDataForPage')
            ->willReturn(['some_field', 'someOther_field', 'someQuiteOther_field']);

        $flexFormUserFunctionsMock->expects(self::once())->method('getConfiguredFacetsForPage')
            ->willReturn([
                'someFacet.' => [
                    'field' => 'some_field',
                    'label' => 'TEXT',
                    'label.' => 'LLL:EXT:some_ext/locallang.xlf:existing_label',
                ],
            ]);
        $parentInformation = [
            'flexParentDatabaseRow' => [
                'pid' => 4711,
            ],
        ];
        $flexFormUserFunctionsMock->getFacetFieldsFromSchema($parentInformation);

        self::assertCount(3, $parentInformation['items']);
        self::assertEquals('some_field (Facet Label: "cObject[...faceting.facets.someFacet.label]")', $parentInformation['items']['some_field'][0]);
    }

    /**
     * @test
     */
    public function passingNullRowReturnsEmptyItems()
    {
        /** @var FlexFormUserFunctions $userFunc */
        $userFunc = $this->getMockBuilder(FlexFormUserFunctions::class)
            ->onlyMethods(['getConfiguredFacetsForPage'])->getMock();

        $userFunc->expects(self::once())->method('getConfiguredFacetsForPage')->willReturn([
            'myType.' => [
                'field' => 'type',
                'label' => 'The type',
            ],
        ]);

        $parentInformation = [
            'flexParentDatabaseRow' => null,
        ];

        $userFunc->getFacetFieldsFromSchema($parentInformation);
        self::assertCount(0, $parentInformation['items']);
    }

    /**
     * @test
     */
    public function canGetExpectedSelectOptions()
    {
        /** @var FlexFormUserFunctions $userFunc */
        $userFunc = $this->getMockBuilder(FlexFormUserFunctions::class)
            ->onlyMethods([
                'getAvailableTemplateFromTypoScriptConfiguration',
                'getConfigurationFromPageId',
            ])->getMock();

        $userFunc->expects(self::once())->method('getAvailableTemplateFromTypoScriptConfiguration')
            ->with(4711, 'results')
            ->willReturn([
            'myTemplate.' => [
                'label' => 'MyCustomTemplate',
                'file' => 'Results',
            ],
        ]);

        $parentInformation = [
            'flexParentDatabaseRow' => [
                'pid' => 4711,
            ],
            'field' => 'view.templateFiles.results',
        ];

        $userFunc->getAvailableTemplates($parentInformation);

        // we expect to get to options, the configured option and a default reset option
        self::assertCount(2, $parentInformation['items']);
    }
}
