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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\ContentObject;

use ApacheSolrForTypo3\Solr\ContentObject\Relation;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

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
        /* @var TypoScriptFrontendController|MockObject $tsfe */
        $GLOBALS['TSFE'] = $tsfe = $this->createMock(TypoScriptFrontendController::class);
        $tsfe->sys_page = GeneralUtility::makeInstance(PageRepository::class);
        /* @var ContentObjectRenderer $contentObjectRendererMock */
        $contentObjectRendererMock = $this->getMockBuilder(ContentObjectRenderer::class)->setConstructorArgs([$GLOBALS['TSFE']])->getMock();
        $contentObjectRendererMock->currentRecord = 'pages:7';

        /* @var Relation $solrRelation */
        $solrRelation = GeneralUtility::makeInstance(Relation::class, $contentObjectRendererMock);
        $actual = $solrRelation->render(['localField' => 'categories']);

        self::assertSame('Some Category', $actual, 'Can not fallback to table "pages" on non existent column configuration in TCA for table "pages_language_overlay".');
    }

    /**
     * canGetRelatedItemsUsingOriginalUidFromPagesTableIfPagesLanguageOverlayTCAHasNoDefinitionForLocalColumn
     *
     * @return array
     */
    public function fixturesProviderForFallbackToPagesTableIfPagesLanguageOverlayTCAHasNoDefinitionForLocalColumn(): array
    {
        return [
            ['solr_relation_can_fallback_to_pages_table_if_no_tca_for_local_field.xml'],
            ['solr_relation_can_get_related_items_using_original_uid_if_sys_lang_overlay_has_no_tca.xml'],
        ];
    }
}
