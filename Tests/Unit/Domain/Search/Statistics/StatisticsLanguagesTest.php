<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Statistics;

use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsLanguages;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Site\Entity\Site;

final class StatisticsLanguagesTest extends SetUpUnitTestCase
{
    #[Test]
    public function offersTheSiteLanguagesTheEditorMayAccess(): void
    {
        $site = new Site('main', 1, [
            'base' => 'https://example.org/',
            'languages' => [
                ['languageId' => 0, 'title' => 'English', 'locale' => 'en_US.UTF-8', 'base' => '/'],
                ['languageId' => 1, 'title' => 'Dansk', 'locale' => 'da_DK.UTF-8', 'base' => '/da/'],
                ['languageId' => 2, 'title' => 'German', 'locale' => 'de_DE.UTF-8', 'base' => '/de/'],
            ],
        ]);
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->method('checkLanguageAccess')->willReturnCallback(static fn(int $languageId): bool => $languageId !== 1);

        self::assertSame([0 => 'English', 2 => 'German'], (new StatisticsLanguages())->forSite($site, $user));
    }
}
