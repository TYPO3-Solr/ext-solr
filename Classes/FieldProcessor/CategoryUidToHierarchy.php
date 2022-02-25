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

namespace ApacheSolrForTypo3\Solr\FieldProcessor;

use ApacheSolrForTypo3\Solr\System\Records\SystemCategory\SystemCategoryRepository;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This Processor takes a UID of sys_category, and resolves its rootline in solr notation.
 *
 * Format of this field corresponds to http://wiki.apache.org/solr/HierarchicalFaceting
 *
 * Let's say we have a category with uid 111 which is a sub category like shown in this tree:
 *
 * 1
 * |-10
 *   |-100
 *     |-111
 *
 * then we get a rootline 1/10/100/111
 *
 * In Solr hierarchy notation, we get
 *
 * 0-1
 * 1-1/10
 * 2-1/10/100
 * 3-1/10/100/11
 *
 * which is finally saved in a multi-value field.
 *
 * @author Steffen Ritter <steffen.ritter@typo3.org>
 */
class CategoryUidToHierarchy extends AbstractHierarchyProcessor implements FieldProcessor
{
    /**
     * @var SystemCategoryRepository
     */
    protected $systemCategoryRepository;

    /**
     * CategoryUidToHierarchy constructor.
     *
     * @param SystemCategoryRepository|null $systemCategoryRepository
     */
    public function __construct(SystemCategoryRepository $systemCategoryRepository = null)
    {
        $this->systemCategoryRepository = $systemCategoryRepository ?? GeneralUtility::makeInstance(SystemCategoryRepository::class);
    }

    /**
     * Expects a uid ID of a category. Returns a Solr hierarchy notation for the
     * rootline of the category ID.
     *
     * @param array $values Array of values, an array because of multivalued fields
     * @return array Modified array of values
     * @throws DBALDriverException
     * @throws DBALException
     */
    public function process(array $values): array
    {
        $results = [];

        foreach ($values as $value) {
            $results = array_merge(
                $results,
                $this->getSolrRootlineForCategoryId($value)
            );
        }

        return $results;
    }

    /**
     * Returns a Solr hierarchy notation string for rootline of given category uid.
     *
     * @param int $categoryId Category ID to get a rootline as Solr hierarchy for
     * @return array Rootline as Solr hierarchy array
     * @throws DBALDriverException
     * @throws DBALException
     */
    protected function getSolrRootlineForCategoryId(int $categoryId): array
    {
        $categoryIdRootline = $this->buildCategoryIdRootline($categoryId);
        return $this->buildSolrHierarchyFromIdRootline($categoryIdRootline);
    }

    /**
     * Builds a category's rootline of parent category Ids
     *
     * @param int $uid The category ID to build the rootline for
     * @return array Category ID rootline as array
     * @throws DBALDriverException
     * @throws DBALException
     */
    protected function buildCategoryIdRootline(int $uid): array
    {
        $rootlineIds = [];
        $parentCategory = $uid;

        while ($parentCategory !== 0) {
            $rootlineIds[] = $parentCategory;
            $childCategory = $this->systemCategoryRepository->findOneByUid($parentCategory);
            if ($childCategory === null) {
                $parentCategory = 0;
            } else {
                $parentCategory = (int)($childCategory['parent']);
            }
        }
        krsort($rootlineIds);

        return array_values($rootlineIds);
    }
}
