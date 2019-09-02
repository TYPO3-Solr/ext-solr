<?php
namespace ApacheSolrForTypo3\Solr\System\Records\SystemLanguage;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-eb-support@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SystemLanguageRepository to encapsulate the database access for records used in solr.
 *
 */
class SystemLanguageRepository extends AbstractRepository implements SingletonInterface
{
    /**
     * @var string
     */
    protected $table = 'sys_language';

    /**
     * Finds the language name for a given language ID.
     *
     * @param int $languageId language ID
     * @return string Language name
     */
    public function findOneLanguageTitleByLanguageId(int $languageId) : string
    {
        $queryBuilder = $this->getQueryBuilder();
        $result = $queryBuilder->select('title')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('uid', $languageId))
            ->execute()->fetch();

        if ($result == false && $languageId == 0) {
            return 'default';
        }

        return isset($result['title']) ? $result['title'] : '';
    }

    /**
     * Finds the system's configured languages.
     *
     * @return array An array of language UIDs
     */
    public function findSystemLanguages()
    {
        $languages = [0];

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        $languageRecords = $queryBuilder->select('uid')
            ->from($this->table)
            ->execute()->fetchAll();

        if ($languageRecords == false) {
            return $languages;
        }

        foreach ($languageRecords as $languageRecord) {
            $languages[] = $languageRecord['uid'];
        }
        return $languages;
    }
}
