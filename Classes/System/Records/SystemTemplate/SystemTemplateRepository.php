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

namespace ApacheSolrForTypo3\Solr\System\Records\SystemTemplate;

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use Doctrine\DBAL\Driver\Exception as DBALDriverException ;

/**
 * SystemTemplateRepository to encapsulate the database access for records used in solr.
 *
 */
class SystemTemplateRepository extends AbstractRepository
{

    /**
     * @var string
     */
    protected $table = 'sys_template';

    /**
     * Finds a first closest page id with active template.
     *
     * This method expects one startPageId, which must be inside the root line and does not check if it is one in the root line.
     *
     * @param array $rootLine
     * @return int
     * @throws DBALDriverException
     */
    public function findOneClosestPageIdWithActiveTemplateByRootLine(array $rootLine): ?int
    {
        $rootLinePageIds = [0];
        foreach ($rootLine as $rootLineItem) {
            $rootLinePageIds[] = (int)$rootLineItem['uid'];
        }

        $queryBuilder = $this->getQueryBuilder();

        $result = $queryBuilder
            ->select('uid', 'pid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('pid', $rootLinePageIds)
            )
            ->execute()->fetchAssociative();

        return $result['pid'] ?? null;
    }
}
