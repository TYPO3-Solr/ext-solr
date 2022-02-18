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

namespace ApacheSolrForTypo3\Solr\System\Records\SystemCategory;

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;

/**
 * Repository class for sys_category items of the TYPO3 system.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SystemCategoryRepository extends AbstractRepository
{
    /**
     * @var string
     */
    protected $table = 'sys_category';

    /**
     * @param int $uid
     * @param string $limitFields
     * @return array
     */
    public function findOneByUid($uid = 0, $limitFields = '*')
    {
        return $this->getOneRowByUid($limitFields, $uid);
    }
}
