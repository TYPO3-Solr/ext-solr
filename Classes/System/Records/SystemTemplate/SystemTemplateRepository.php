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

namespace ApacheSolrForTypo3\Solr\System\Records\SystemTemplate;

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use Doctrine\DBAL\Exception as DBALException;

/**
 * SystemTemplateRepository to encapsulate the database access for records used in solr.
 */
class SystemTemplateRepository extends AbstractRepository
{
    protected string $table = 'sys_template';

    /**
     * Finds a first closest page id with active template.
     * This method expects one startPageId, which must be inside the root line and does not check if it is one in the root line.
     *
     * @throws DBALException
     */
    public function findOneClosestPageIdWithActiveTemplateByRootLine(array $rootLine): ?int
    {
        if (empty($rootLine)) {
            return null;
        }

        $rootLinePageIds = [0];
        $rootLinePositions = [];

        // Create position mapping for sorting later
        foreach ($rootLine as $position => $rootLineItem) {
            $pageId = (int)$rootLineItem['uid'];
            $rootLinePageIds[] = $pageId;
            $rootLinePositions[$pageId] = $position;
        }

        $queryBuilder = $this->getQueryBuilder();

        // Get all pages with templates in the rootline
        $templatesInRootline = $queryBuilder
            ->select('uid', 'pid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in(
                    'pid',
                    $queryBuilder->createNamedParameter(
                        $rootLinePageIds,
                        \Doctrine\DBAL\ArrayParameterType::INTEGER
                    )
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        if (empty($templatesInRootline)) {
            return null;
        }

        usort($templatesInRootline, function ($a, $b) use ($rootLinePositions) {
            return $rootLinePositions[$b['pid']] <=> $rootLinePositions[$a['pid']];
        });

        return isset($templatesInRootline[0]['pid']) ? (int)$templatesInRootline[0]['pid'] : null;
    }
}
