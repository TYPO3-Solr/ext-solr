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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Statistics;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * The languages the statistics can be filtered by
 */
final class StatisticsLanguages
{
    /**
     * @return array<int, string> language ID => title, only languages the editor may access
     */
    public function forSite(Site $site, BackendUserAuthentication $backendUser): array
    {
        $languages = [];
        foreach ($site->getLanguages() as $language) {
            if ($backendUser->checkLanguageAccess($language->getLanguageId())) {
                $languages[$language->getLanguageId()] = $language->getTitle();
            }
        }
        ksort($languages);

        return $languages;
    }
}
