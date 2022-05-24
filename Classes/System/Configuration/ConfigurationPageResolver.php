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

namespace ApacheSolrForTypo3\Solr\System\Configuration;

use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Records\SystemTemplate\SystemTemplateRepository;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use RuntimeException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * This class is responsible to find the closest page id from the rootline where
 * a typoscript template is stored on.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ConfigurationPageResolver
{

    /**
     * @var SystemTemplateRepository
     */
    protected $systemTemplateRepository;

    /**
     * @var TwoLevelCache
     */
    protected $runtimeCache;

    /**
     * ConfigurationPageResolver constructor.
     * @param TwoLevelCache|null $twoLevelCache
     * @param SystemTemplateRepository|null $systemTemplateRepository
     */
    public function __construct(?TwoLevelCache $twoLevelCache = null, ?SystemTemplateRepository $systemTemplateRepository = null)
    {
        $this->runtimeCache = $twoLevelCache ?? GeneralUtility::makeInstance(TwoLevelCache::class, /** @scrutinizer ignore-type */ 'runtime');
        $this->systemTemplateRepository = $systemTemplateRepository ?? GeneralUtility::makeInstance(SystemTemplateRepository::class);
    }

    /**
     * This method fetches the rootLine and calculates the id of the closest template in the rootLine.
     * The result is stored in the runtime cache.
     *
     * @param int $startPageId
     * @return int
     * @throws DBALDriverException
     */
    public function getClosestPageIdWithActiveTemplate(int $startPageId): ?int
    {
        if ($startPageId === 0) {
            return null;
        }

        $cacheId = 'ConfigurationPageResolver' . '_' . 'getClosestPageIdWithActiveTemplate' . '_' . $startPageId;
        $methodResult = $this->runtimeCache->get($cacheId);
        if (!empty($methodResult)) {
            return $methodResult;
        }

        $methodResult = $this->calculateClosestPageIdWithActiveTemplate($startPageId);
        $this->runtimeCache->set($cacheId, $methodResult);

        return $methodResult;
    }

    /**
     * This method fetches the rootLine and calculates the id of the closest template in the rootLine.
     *
     * @param int $startPageId
     * @return int
     * @throws DBALDriverException
     */
    protected function calculateClosestPageIdWithActiveTemplate(int $startPageId): ?int
    {
        /* @var RootlineUtility $rootlineUtility */
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $startPageId);
        try {
            $rootline = $rootlineUtility->get();
        } catch (RuntimeException $e) {
            return $startPageId;
        }

        $closestPageIdWithTemplate = $this->systemTemplateRepository->findOneClosestPageIdWithActiveTemplateByRootLine($rootline);
        if ($closestPageIdWithTemplate === 0) {
            return null;
        }

        return $closestPageIdWithTemplate;
    }
}
