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

namespace ApacheSolrForTypo3\Solr\System\Records\SystemCategory;

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use Doctrine\DBAL\Exception as DBALException;

/**
 * Repository class for sys_category items of the TYPO3 system.
 */
class SystemCategoryRepository extends AbstractRepository
{
    protected string $table = 'sys_category';

    /**
     * Finds one sys_category by UID
     *
     * @throws DBALException
     */
    public function findOneByUid(int $uid = 0, string $limitFields = '*'): array|bool
    {
        return $this->getOneRowByUid($limitFields, $uid);
    }
}
