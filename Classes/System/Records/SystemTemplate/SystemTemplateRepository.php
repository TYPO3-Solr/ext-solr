<?php
namespace ApacheSolrForTypo3\Solr\System\Records\SystemTemplate;

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
     */
    public function findOneClosestPageIdWithActiveTemplateByRootLine(array $rootLine)
    {
        $rootLinePageIds = [0];
        foreach ($rootLine as $rootLineItem) {
            $rootLinePageIds[] = (int)$rootLineItem['uid'];
        }

        $queryBuilder = $this->getQueryBuilder();

        $result = $queryBuilder
            ->select('uid', 'pid')
            ->from($this->table)
            ->where($queryBuilder->expr()->in('pid', $rootLinePageIds))
            ->execute()->fetch();

        return isset($result['pid']) ? $result['pid'] : 0;
    }
}
