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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class RelationTest
 */
class RelationTest extends IntegrationTest
{
    /**
     * @param string $fixtureName
     *
     * @test
     * @dataProvider fixturesProviderForFallbackToPagesTableIfPagesLanguageOverlayTCAHasNoDefinitionForLocalColumn
     */
    public function canFallbackToPagesTableIfPagesLanguageOverlayTCAHasNoDefinitionForLocalColumn(string $fixtureName): void
    {
        $this->importDataSetFromFixture($fixtureName);
        $solrRelation = $this->getSolrRelation('pages', 7);
        $actual = $solrRelation->render(['localField' => 'categories']);

        self::assertSame('Some Category', $actual, 'Can not fallback to table "pages" on non existent column configuration in TCA for table "pages_language_overlay".');
    }

    /**
     * Data provider for "canFallbackToPagesTableIfPagesLanguageOverlayTCAHasNoDefinitionForLocalColumn"
     *
     * @return \Traversable
     */
    public function fixturesProviderForFallbackToPagesTableIfPagesLanguageOverlayTCAHasNoDefinitionForLocalColumn(): \Traversable
    {
        yield 'Can fallback to pages if no TCA for local field'
            => ['solr_relation_can_fallback_to_pages_table_if_no_tca_for_local_field.xml'];
        yield 'Can get related items using original uid if overlay has no TCA'
            => ['solr_relation_can_get_related_items_using_original_uid_if_sys_lang_overlay_has_no_tca.xml'];
    }

    /**
     * @param string $expected
     * @param array $config
     *
     * @test
     * @dataProvider canResolveOneToOneRelationDataProvider
     */
    public function canResolveOneToOneRelation(string $expected, array $config): void
    {
        $this->importExtTablesDefinition('fake_extension.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('TCA/tx_fakeextension_domain_model_foo.php'));
        $this->importDataSetFromFixture('solr_relation_can_resolve_one_to_one_relations.xml');

        $solrRelation = $this->getSolrRelation('tx_fakeextension_domain_model_foo', 1);
        self::assertEquals($expected, $solrRelation->render($config));
    }

    /**
     * Data provider for "canResolveOneToOneRelation"
     *
     * @return \Traversable
     */
    public function canResolveOneToOneRelationDataProvider(): \Traversable
    {
        yield 'Can resolve title of 1:1 relation' => [
            'Second category',
            ['localField' => 'main_category'],
        ];

        yield 'Can resolve description field of 1:1 relation' => [
            'This is the second category',
            ['localField' => 'main_category', 'foreignLabelField' => 'description'],
        ];

        yield 'Can resolve uid of 1:1 relation' => [
            '124',
            ['localField' => 'main_category', 'foreignLabelField' => 'uid'],
        ];

        yield 'Can resolve uid of parent in 1:1 relation' => [
            '123',
            ['localField' => 'main_category', 'foreignLabelField' => 'parent.uid'],
        ];

        yield 'Can resolve title of parent in 1:1 relation' => [
            'Main category',
            ['localField' => 'main_category', 'foreignLabelField' => 'parent'],
        ];

        yield 'Can resolve title of 1:1 relation and apply stdWrap' => [
            'pre:Second category:post',
            ['localField' => 'main_category', 'wrap' => 'pre:|:post'],
        ];
    }

    /**
     * @param string $expected
     * @param string $table
     * @param int $recordUid
     * @param array $config
     *
     * @test
     * @dataProvider canResolveMToNRelationDataProvider
     */
    public function canResolveMToNRelation(string $expected, string $table, int $recordUid, array $config): void
    {
        $this->importExtTablesDefinition('fake_extension.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('TCA/tx_fakeextension_domain_model_foo.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('TCA/tx_fakeextension_domain_model_bar.php'));
        $this->importDataSetFromFixture('solr_relation_can_resolve_m_to_n_relations.xml');

        $solrRelation = $this->getSolrRelation($table, $recordUid);
        $result = $solrRelation->render($config);
        self::assertEquals($expected, $result);
    }

    /**
     * Data provider for "canResolveMToNRelation"
     *
     * @return \Traversable
     */
    public function canResolveMToNRelationDataProvider(): \Traversable
    {
        yield 'Can resolve title of m:n relation and apply stdWrap' => [
            'pre:First bar record:post',
            'tx_fakeextension_domain_model_foo',
            1,
            ['localField' => 'mm_assignments', 'wrap' => 'pre:|:post'],
        ];

        yield 'Can resolve title of m:n relation' => [
            'First bar record',
            'tx_fakeextension_domain_model_foo',
            1,
            ['localField' => 'mm_assignments'],
        ];

        yield 'Can resolve title of relation\'s main category in m:n relation' => [
            'Second category',
            'tx_fakeextension_domain_model_foo',
            1,
            ['localField' => 'mm_assignments', 'foreignLabelField' => 'main_category'],
        ];

        yield 'Can resolve explicitly configured title of relation\'s main category in m:n relation' => [
            'Second category',
            'tx_fakeextension_domain_model_foo',
            1,
            ['localField' => 'mm_assignments', 'foreignLabelField' => 'main_category.title'],
        ];

        yield 'Can resolve title of parent category of relation\'s main category in m:n relation' => [
            'Main category',
            'tx_fakeextension_domain_model_foo',
            1,
            ['localField' => 'mm_assignments', 'foreignLabelField' => 'main_category.title.parent'],
        ];

        yield 'Can resolve description of parent category of relation\'s main category in m:n relation' => [
            'This is the main category',
            'tx_fakeextension_domain_model_foo',
            1,
            ['localField' => 'mm_assignments', 'foreignLabelField' => 'main_category.title.parent.description'],
        ];

        yield 'Can resolve title of m:n relation, with 2 items' => [
            'Second bar record, First bar record',
            'tx_fakeextension_domain_model_foo',
            2,
            ['localField' => 'mm_assignments'],
        ];

        yield 'Can resolve titles of the main categories of the related records in m:n relation' => [
            serialize(['Third category', 'Second category']),
            'tx_fakeextension_domain_model_foo',
            2,
            ['localField' => 'mm_assignments', 'foreignLabelField' => 'main_category', 'multiValue' => 1],
        ];

        yield 'Can resolve titles of the main categories of the related records in m:n relation and keep duplicates' => [
            serialize(['Third category', 'Second category', 'Second category']),
            'tx_fakeextension_domain_model_foo',
            3,
            ['localField' => 'mm_assignments', 'foreignLabelField' => 'main_category', 'multiValue' => 1],
        ];

        yield 'Can resolve titles of the main categories of the related records in m:n relation and remove duplicates' => [
            serialize(['Third category', 'Second category']),
            'tx_fakeextension_domain_model_foo',
            3,
            ['localField' => 'mm_assignments', 'foreignLabelField' => 'main_category', 'multiValue' => 1, 'removeDuplicateValues' => 1],
        ];

        yield 'Can resolve uids of m:n relation, with 2 items' => [
            '11, 10',
            'tx_fakeextension_domain_model_foo',
            2,
            ['localField' => 'mm_assignments', 'foreignLabelField' => 'uid'],
        ];

        yield 'Can resolve uids of m:n relation for multi value field, with 2 items' => [
            serialize(['11', '10']),
            'tx_fakeextension_domain_model_foo',
            2,
            ['localField' => 'mm_assignments', 'foreignLabelField' => 'uid', 'multiValue' => 1],
        ];

        yield 'Can resolve title of bidirectional m:n relation' => [
            'Third foo record',
            'tx_fakeextension_domain_model_bar',
            12,
            ['localField' => 'mm_assignments'],
        ];
    }

    /**
     * @param string $expected
     * @param string $table
     * @param int $recordUid
     * @param array $config
     *
     * @test
     * @dataProvider canResolveOneToNRelationDataProvider
     */
    public function canResolveOneToNRelation(string $expected, string $table, int $recordUid, array $config): void
    {
        $this->importExtTablesDefinition('fake_extension.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('TCA/tx_fakeextension_domain_model_foo.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('TCA/tx_fakeextension_domain_model_bar.php'));
        $this->importDataSetFromFixture('solr_relation_can_resolve_one_to_n_relations.xml');

        $solrRelation = $this->getSolrRelation($table, $recordUid);
        $result = $solrRelation->render($config);
        self::assertEquals($expected, $result);
    }

    /**
     * Data provider for "canResolveOneToNRelation"
     *
     * @return \Traversable
     */
    public function canResolveOneToNRelationDataProvider(): \Traversable
    {
        yield 'Can resolve title of single 1:n relation' => [
            'First bar record',
            'tx_fakeextension_domain_model_foo',
            1,
            ['localField' => 'inline_relations'],
        ];

        yield 'Can resolve titles of 1:n relation' => [
            'Second bar record, Third bar record',
            'tx_fakeextension_domain_model_foo',
            2,
            ['localField' => 'inline_relations'],
        ];

        yield 'Can resolve titles of related records 1:n relation' => [
            'Third foo record, 4th foo record',
            'tx_fakeextension_domain_model_foo',
            1,
            ['localField' => 'inline_relations', 'foreignLabelField' => 'mm_assignments.title', 'multiValue' => 0],
        ];

        yield 'Can resolve uids of related records 1:n relation' => [
            '3, 4',
            'tx_fakeextension_domain_model_foo',
            1,
            ['localField' => 'inline_relations', 'foreignLabelField' => 'mm_assignments.uid', 'multiValue' => 0],
        ];

        yield 'Can resolve related categories of related records 1:n relation' => [
            'Second category, Second category',
            'tx_fakeextension_domain_model_foo',
            1,
            ['localField' => 'inline_relations', 'foreignLabelField' => 'mm_assignments.main_category', 'multiValue' => 0],
        ];

        yield 'Can resolve related categories of related records 1:n relation (multi value)' => [
            serialize(['Second category', 'Second category']),
            'tx_fakeextension_domain_model_foo',
            1,
            ['localField' => 'inline_relations', 'foreignLabelField' => 'mm_assignments.main_category', 'multiValue' => 1],
        ];

        yield 'Can resolve related categories of related records 1:n relation and remove duplicates' => [
            'Second category',
            'tx_fakeextension_domain_model_foo',
            1,
            ['localField' => 'inline_relations', 'foreignLabelField' => 'mm_assignments.main_category', 'removeDuplicateValues' => 1],
        ];
    }

    /**
     * Prepares and returns the Relation to test
     *
     * @param string $table
     * @param int $uid
     * @return Relation
     */
    protected function getSolrRelation(string $table, int $uid): Relation
    {
        $tsfeMock = $this->createMock(TypoScriptFrontendController::class);
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class, $tsfeMock);
        $contentObjectRenderer->start(
            BackendUtility::getRecord($table, $uid),
            $table
        );

        return GeneralUtility::makeInstance(Relation::class, $contentObjectRenderer);
    }
}
