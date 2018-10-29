<?php
namespace ApacheSolrForTypo3\Solr\Test\System\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\System\UserFunctions\FlexFormUserFunctions;
use TYPO3\CMS\Extbase\Service\TypoScriptService;
use TYPO3\CMS\Lang\LanguageService;

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
            ->setMethods(['getFieldNamesFromSolrMetaDataForPage', 'getConfiguredFacetsForPage'])->getMock();

        $userFunc->expects($this->once())->method('getFieldNamesFromSolrMetaDataForPage')->will($this->returnValue(['type', 'pid', 'uid']));
        $userFunc->expects($this->once())->method('getConfiguredFacetsForPage')->will($this->returnValue([]));

        $parentInformation = [
            'flexParentDatabaseRow' => [
                'pid' => 4711
            ]
        ];

        $userFunc->getFacetFieldsFromSchema($parentInformation);
        $this->assertCount(3, $parentInformation['items']);
        $this->assertEquals(0, $parentInformation['items'][0][0]);
    }

    /**
     * @test
     */
    public function labelIsUsedFromFacetWhenTheFacetIsConfiguredInTypoScript()
    {
        /** @var FlexFormUserFunctions $userFunc */
        $userFunc = $this->getMockBuilder(FlexFormUserFunctions::class)
            ->setMethods(['getFieldNamesFromSolrMetaDataForPage', 'getConfiguredFacetsForPage'])->getMock();

        $userFunc->expects($this->once())->method('getFieldNamesFromSolrMetaDataForPage')->will($this->returnValue(['type', 'pid', 'uid']));
        $userFunc->expects($this->once())->method('getConfiguredFacetsForPage')->will($this->returnValue([
            'myType.' => [
                'field' => 'type',
                'label' => 'The type'
            ]
        ]));

        $parentInformation = [
            'flexParentDatabaseRow' => [
                'pid' => 4711
            ]
        ];

        $userFunc->getFacetFieldsFromSchema($parentInformation);
        $this->assertCount(3, $parentInformation['items']);
        $this->assertEquals('type (Facet Label: "The type")', $parentInformation['items']['type'][0]);
    }

    /**
     * @test
     */
    public function duplicateFacetLabelDoesNotMakeFieldsDisappearingInFlexForms()
    {
        /** @var FlexFormUserFunctions $flexFormUserFunctionsMock */
        $flexFormUserFunctionsMock = $this->getMockBuilder(FlexFormUserFunctions::class)
            ->setMethods(['getFieldNamesFromSolrMetaDataForPage', 'getConfiguredFacetsForPage'])->getMock();
        $flexFormUserFunctionsMock->expects($this->once())->method('getFieldNamesFromSolrMetaDataForPage')
            ->will($this->returnValue(['some_field', 'someOther_field'])
        );

        $flexFormUserFunctionsMock->expects($this->once())->method('getConfiguredFacetsForPage')
            ->will($this->returnValue([
                'someFacet.' => [
                    'field' => 'some_field',
                    'label' => 'TEXT'
                ],
                'someOtherFacet.' => [
                    'field' => 'someOther_field',
                    'label' => 'TEXT'
                ]
            ]));

        $parentInformation = [
            'flexParentDatabaseRow' => [
                'pid' => 4711
            ]
        ];
        $flexFormUserFunctionsMock->getFacetFieldsFromSchema($parentInformation);
        $this->assertCount(2, $parentInformation['items']);
    }

    /**
     * @test
     */
    public function facetLabelIsShownTranslatedInBracketsSignsInFlexFormsIfTranslationIsAvailable()
    {
        /** @var FlexFormUserFunctions $flexFormUserFunctionsMock */
        $flexFormUserFunctionsMock = $this->getMockBuilder(FlexFormUserFunctions::class)
            ->setMethods(['getFieldNamesFromSolrMetaDataForPage', 'getConfiguredFacetsForPage'])->getMock();
        $flexFormUserFunctionsMock->expects($this->once())->method('getFieldNamesFromSolrMetaDataForPage')
            ->will($this->returnValue(['some_field', 'someOther_field', 'someQuiteOther_field', 'uid', 'pid']));

        $flexFormUserFunctionsMock->expects($this->once())->method('getConfiguredFacetsForPage')
            ->will($this->returnValue([
                'someFacet.' => [
                    'field' => 'some_field',
                    'label' => 'LLL:EXT:some_ext/locallang.xlf:existing_label'
                ],
                'someOtherFacet.' => [
                    'field' => 'someOther_field',
                    'label' => 'LLL:EXT:some_ext/locallang.xlf:not_existing_label'
                ],
                'someQuiteOtherFacet.' => [
                    'field' => 'someQuiteOther_field',
                    'label' => 'LLL:EXT:some_ext/locallang.xlf:not_existing_label'
                ]
            ]));

        /** @var LanguageService $languageServiceMock */
        $languageServiceMock = $this->getMockBuilder(LanguageService::class)->disableOriginalConstructor()->setMethods(['sL'])->getMock();
        $languageServiceMock->expects($this->any())->method('sL')->will(
            $this->returnCallback(function() {
                $args = func_get_args();
                if ($args[0] === 'LLL:EXT:some_ext/locallang.xlf:existing_label') {
                    return 'Translated Facet';
                }
                return '';
            })
        );
        $GLOBALS['LANG'] = $languageServiceMock;

        $parentInformation = [
            'flexParentDatabaseRow' => [
                'pid' => 4711
            ]
        ];
        $flexFormUserFunctionsMock->getFacetFieldsFromSchema($parentInformation);

        $this->assertCount(5, $parentInformation['items']);
        $this->assertEquals('some_field (Facet Label: "Translated Facet")', $parentInformation['items']['some_field'][0]);
        $this->assertEquals('someOther_field (Facet Label: "LLL:EXT:some_ext/locallang.xlf:not_existing_label")', $parentInformation['items']['someOther_field'][0]);
        $this->assertEquals('someQuiteOther_field (Facet Label: "LLL:EXT:some_ext/locallang.xlf:not_existing_label")', $parentInformation['items']['someQuiteOther_field'][0]);
    }

    /**
     * @test
     */
    public function cObjectPathIsShownInBracketsSignsInFlexFormsIfcObjectIsUsed()
    {
        /** @var FlexFormUserFunctions $flexFormUserFunctionsMock */
        $flexFormUserFunctionsMock = $this->getMockBuilder(FlexFormUserFunctions::class)
            ->setMethods(['getFieldNamesFromSolrMetaDataForPage', 'getConfiguredFacetsForPage'])->getMock();
        $flexFormUserFunctionsMock->expects($this->once())->method('getFieldNamesFromSolrMetaDataForPage')
            ->will($this->returnValue(['some_field', 'someOther_field', 'someQuiteOther_field']));

        $flexFormUserFunctionsMock->expects($this->once())->method('getConfiguredFacetsForPage')
            ->will($this->returnValue([
                'someFacet.' => [
                    'field' => 'some_field',
                    'label' => 'TEXT',
                    'label.' => 'LLL:EXT:some_ext/locallang.xlf:existing_label'
                ]
            ]));
        $parentInformation = [
            'flexParentDatabaseRow' => [
                'pid' => 4711
            ]
        ];
        $flexFormUserFunctionsMock->getFacetFieldsFromSchema($parentInformation);

        $this->assertCount(3, $parentInformation['items']);
        $this->assertEquals('some_field (Facet Label: "cObject[...faceting.facets.someFacet.label]")', $parentInformation['items']['some_field'][0]);
    }

    /**
     * @test
     */
    public function passingNullRowReturnsEmptyItems()
    {
        /** @var FlexFormUserFunctions $userFunc */
        $userFunc = $this->getMockBuilder(FlexFormUserFunctions::class)
            ->setMethods(['getConfiguredFacetsForPage'])->getMock();

        $userFunc->expects($this->once())->method('getConfiguredFacetsForPage')->will($this->returnValue([
            'myType.' => [
                'field' => 'type',
                'label' => 'The type'
            ]
        ]));

        $parentInformation = [
            'flexParentDatabaseRow' => null
        ];

        $userFunc->getFacetFieldsFromSchema($parentInformation);
        $this->assertCount(0, $parentInformation['items']);
    }

    /**
     * @test
     */
    public function canGetExpectedSelectOptions()
    {
        /** @var FlexFormUserFunctions $userFunc */
        $userFunc = $this->getMockBuilder(FlexFormUserFunctions::class)
            ->setMethods([
                'getAvailableTemplateFromTypoScriptConfiguration',
                'getConfigurationFromPageId'
            ])->getMock();

        $userFunc->expects($this->once())->method('getAvailableTemplateFromTypoScriptConfiguration')
            ->with(4711, 'results')
            ->will($this->returnValue([
            'myTemplate.' => [
                'label' => 'MyCustomTemplate',
                'file' => 'Results'
            ]
        ]));

        $parentInformation = [
            'flexParentDatabaseRow' => [
                'pid' => 4711,
            ],
            'field' => 'view.templateFiles.results'
        ];

        $userFunc->getAvailableTemplates($parentInformation);

        // we expect to get to options, the configured option and a default reset option
        $this->assertCount(2, $parentInformation['items']);
    }

}
