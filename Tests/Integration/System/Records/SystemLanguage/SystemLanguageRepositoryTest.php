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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Records\SystemLanguage;

use ApacheSolrForTypo3\Solr\System\Records\SystemLanguage\SystemLanguageRepository;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SystemLanguageRepository to encapsulate the database access for records used in solr.
 */
class SystemLanguageRepositoryTest extends IntegrationTest
{
    /**
     * @test
     */
    public function canFindOneLanguageTitleByLanguageId()
    {
        $this->importDataSetFromFixture('sys_language.xml');

        /* @var $repository SystemLanguageRepository */
        $repository = GeneralUtility::makeInstance(SystemLanguageRepository::class);
        $languageTitle = $repository->findOneLanguageTitleByLanguageId(1);
        self::assertEquals('English', $languageTitle);
    }

    /**
     * @test
     */
    public function findOneLanguageTitleByLanguageIdReturnsDefaultIfLanguageIdIs0AndNoLanguagesAreDefined()
    {
        /* @var $repository SystemLanguageRepository */
        $repository = GeneralUtility::makeInstance(SystemLanguageRepository::class);
        $languageTitle = $repository->findOneLanguageTitleByLanguageId(0);
        self::assertEquals('default', $languageTitle);
    }

    /**
     * @test
     */
    public function canFindSystemLanguages()
    {
        $this->importDataSetFromFixture('sys_language.xml');

        /* @var $repository SystemLanguageRepository */
        $repository = GeneralUtility::makeInstance(SystemLanguageRepository::class);
        $systemLanguages = $repository->findSystemLanguages();

        $expectedLangueages = [0, 1, 2, 3];
        self::assertSame($expectedLangueages, $systemLanguages);
    }
}
