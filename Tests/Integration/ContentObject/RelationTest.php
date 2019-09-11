<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\ContentObject;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2018 dkd Internet Service GmbH <solr-eb-support@dkd.de>
 *
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


use ApacheSolrForTypo3\Solr\ContentObject\Relation;
use ApacheSolrForTypo3\Solr\System\Mvc\Frontend\Controller\OverriddenTypoScriptFrontendController;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Class RelationTest
 */
class RelationTest extends IntegrationTest
{

    /**
     * @test
     *
     * @dataProvider fixturesProviderForFallbackToPagesTableIfPagesLanguageOverlayTCAHasNoDefinitionForLocalColumn
     */
    public function canFallbackToPagesTableIfPagesLanguageOverlayTCAHasNoDefinitionForLocalColumn($fixtureName)
    {
        $this->importDataSetFromFixture($fixtureName);
        $GLOBALS['TSFE'] = $this->getMockBuilder(OverriddenTypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
        /* @var ContentObjectRenderer $contentObjectRenderer */
        $contentObjectRendererMock = $this->getMockBuilder(ContentObjectRenderer::class)->setConstructorArgs([$GLOBALS['TSFE']])->getMock();
        $contentObjectRendererMock->currentRecord = 'pages:7';
        $GLOBALS['TSFE']->sys_language_uid = 1;

        /* @var Relation $solrRelation */
        $solrRelation = GeneralUtility::makeInstance(Relation::class);
        $actual = $solrRelation->cObjGetSingleExt(Relation::CONTENT_OBJECT_NAME, ['localField' => 'categories'], null, $contentObjectRendererMock);

        $this->assertSame('Some Category', $actual, 'Can not fallback to table "pages" on non existent column configuration in TCA for table "pages_language_overlay".');
    }

    /**
     * canGetRelatedItemsUsingOriginalUidFromPagesTableIfPagesLanguageOverlayTCAHasNoDefinitionForLocalColumn
     *
     * @return array
     */
    public function fixturesProviderForFallbackToPagesTableIfPagesLanguageOverlayTCAHasNoDefinitionForLocalColumn()
    {
        return [
            ['solr_relation_can_fallback_to_pages_table_if_no_tca_for_local_field.xml'],
            ['solr_relation_can_get_related_items_using_original_uid_if_sys_lang_overlay_has_no_tca.xml']
        ];
    }
}
