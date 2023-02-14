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

namespace ApacheSolrForTypo3\Solr\System\Records\SystemLanguage;

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SystemLanguageRepository to encapsulate the database access for records used in solr.
 */
class SystemLanguageRepository extends AbstractRepository implements SingletonInterface
{
    /**
     * @var string
     */
    protected string $table = 'sys_language';

    /**
     * Finds the language name for a given language ID.
     *
     * @param int $languageId language ID
     * @return string Language name
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function findOneLanguageTitleByLanguageId(int $languageId): string
    {
        $queryBuilder = $this->getQueryBuilder();
        $result = $queryBuilder->select('title')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('uid', $languageId))
            ->execute()
            ->fetchAssociative();

        if ($result == false && $languageId == 0) {
            return 'default';
        }

        return $result['title'] ?? '';
    }

    /**
     * Finds the system's configured languages.
     *
     * @return array An array of language UIDs
     * @throws DBALException|\Doctrine\DBAL\DBALException
     * @throws DBALDriverException
     */
    public function findSystemLanguages(): array
    {
        $languages = [0];

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        $languageRecords = $queryBuilder->select('uid')
            ->from($this->table)
            ->execute()
            ->fetchAllAssociative();

        if ($languageRecords == false) {
            return $languages;
        }

        foreach ($languageRecords as $languageRecord) {
            $languages[] = $languageRecord['uid'];
        }
        return $languages;
    }
}
