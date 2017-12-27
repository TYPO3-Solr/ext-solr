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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext;
use ApacheSolrForTypo3\Solr\System\Service\ConfigurationService;
use ApacheSolrForTypo3\Solr\System\UserFunctions\FlexFormUserFunctions;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\FlexFormService;
use TYPO3\CMS\Extbase\Service\TypoScriptService;

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
        $this->assertEquals('The type', $parentInformation['items']['The type'][0]);
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
